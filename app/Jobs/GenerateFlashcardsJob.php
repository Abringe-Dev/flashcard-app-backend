<?php

namespace App\Jobs;

use App\Models\FlashcardSet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GenerateFlashcardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public FlashcardSet $flashcardSet) {}

    public function handle(): void
    {
        try {
            $content = $this->flashcardSet->original_content;

            if (empty(trim($content))) {
                $this->flashcardSet->update(['status' => 'failed']);
                return;
            }

            $content = mb_substr($content, 4000);

$randomSeed = rand(1, 1000);
$prompt = "Based on the following content, generate 10 unique flashcards for studying.
Use random seed {$randomSeed} to vary your selection.
Focus on DIFFERENT aspects each time - definitions, examples, applications, comparisons.
Do not repeat the same questions from previous generations.
Return ONLY a valid JSON array, no explanation, no markdown, no code blocks.
Format: [{\"question\": \"...\", \"answer\": \"...\"}, ...]

Content:
{$content}";

            $apiKey = env('GROQ_API_KEY');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
            ]);

            $data = $response->json();

            $rawText = $data['choices'][0]['message']['content'] ?? null;

            if (!$rawText) {
                Log::error('Groq returned empty response', ['data' => $data]);
                $this->flashcardSet->update(['status' => 'failed']);
                return;
            }

            $cleaned = preg_replace('/```json|```/i', '', $rawText);
            $cleaned = trim($cleaned);

            $flashcards = json_decode($cleaned, true);

            if (!is_array($flashcards) || empty($flashcards)) {
                Log::error('Groq returned invalid JSON', ['raw' => $rawText]);
                $this->flashcardSet->update(['status' => 'failed']);
                return;
            }

            foreach ($flashcards as $card) {
                if (!isset($card['question'], $card['answer'])) continue;

                $this->flashcardSet->flashcards()->create([
                    'question' => $card['question'],
                    'answer' => $card['answer'],
                    'difficulty' => 1,
                ]);
            }

            $this->flashcardSet->update(['status' => 'completed']);

        } catch (\Exception $e) {
            Log::error('GenerateFlashcardsJob failed: ' . $e->getMessage());
            $this->flashcardSet->update(['status' => 'failed']);
        }
    }
}