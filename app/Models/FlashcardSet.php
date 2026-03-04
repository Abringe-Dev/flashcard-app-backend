<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashcardSet extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'file_name',
        'file_path',
        'original_content',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function flashcards()
    {
        return $this->hasMany(Flashcard::class);
    }
}