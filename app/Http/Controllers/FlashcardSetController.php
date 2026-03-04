<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\FlashcardSet;
use App\Jobs\GenerateFlashcardsJob;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;

class FlashcardSetController extends Controller
{
    public function index(Request $request) {
        $sets = $request->user()->flashcardSets()
            ->with('flashcards')
            ->latest()
            ->get();

        return response()->json($sets);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:txt,pdf,docx,pptx|max:10240',
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('uploads', $fileName, 'public');

        $content = $this->extractTextFromFile($file);

        $set = FlashcardSet::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'file_name' => $fileName,
            'file_path' => $filePath,
            'original_content' => $content,
            'status' => 'processing'
        ]);

        GenerateFlashcardsJob::dispatch($set);

        return response()->json([
            'message' => 'File uploaded successfully',
            'set' => $set
        ], 201);
    }

    public function show($id, Request $request) {
        $set = FlashcardSet::with('flashcards')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($set);
    }

    public function destroy($id, Request $request) {
        $set = FlashcardSet::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($set->file_path) {
            Storage::disk('public')->delete($set->file_path);
        }

        $set->delete();

        return response()->json(['message' => 'Flashcard set deleted']);
    }

    private function extractTextFromFile($file) {
        $extension = $file->getClientOriginalExtension();

        try {
            switch ($extension) {
                case 'txt':
                    return file_get_contents($file->getRealPath());

                case 'pdf':
                    $parser = new PdfParser();
                    $pdf = $parser->parseFile($file->getRealPath());
                    return $pdf->getText();

                case 'docx':
                    $phpWord = IOFactory::load($file->getRealPath());
                    $text = '';
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                foreach ($element->getElements() as $textElement) {
                                    if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                        $text .= $textElement->getText();
                                    }
                                }
                                $text .= "\n";
                            } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $element->getText() . "\n";
                            } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                                foreach ($element->getRows() as $row) {
                                    foreach ($row->getCells() as $cell) {
                                        foreach ($cell->getElements() as $cellElement) {
                                            if ($cellElement instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                                foreach ($cellElement->getElements() as $textElement) {
                                                    if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                                        $text .= $textElement->getText() . ' ';
                                                    }
                                                }
                                            } elseif ($cellElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                                $text .= $cellElement->getText() . ' ';
                                            }
                                        }
                                    }
                                    $text .= "\n";
                                }
                            }
                        }
                    }
                    return $text;

                case 'pptx':
                    $text = '';
                    $zip = new \ZipArchive();
                    if ($zip->open($file->getRealPath()) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $name = $zip->getNameIndex($i);
                            if (preg_match('/ppt\/slides\/slide[0-9]+\.xml$/', $name)) {
                                $xml = $zip->getFromIndex($i);
                                $dom = new \DOMDocument();
                                @$dom->loadXML($xml);
                                $xpath = new \DOMXPath($dom);
                                $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                                $nodes = $xpath->query('//a:t');
                                foreach ($nodes as $node) {
                                    $text .= $node->nodeValue . ' ';
                                }
                                $text .= "\n";
                            }
                        }
                        $zip->close();
                    }
                    return $text;

                default:
                    return '';
            }
        } catch (\Exception $e) {
            Log::error('File extraction error: ' . $e->getMessage());
            return '';
        }
    }
    public function regenerate($id, Request $request) {
        $set = FlashcardSet::where('user_id', $request->user()->id)
        ->findOrFail($id);

        $set->flashcards()->delete();

        $set->update(['status' => 'processing']);

        return response()->json(['message' => 'Regenerating flashcards....']);
    }
}