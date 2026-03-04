<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model
{
    protected $fillable = [
        'flashcard_set_id',
        'question',
        'answer',
        'difficulty'
    ];

    public function flashcardSet()
    {
        return $this->belongsTo(FlashcardSet::class);
    }
}