<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory;


    protected $fillable = ['user_id', 'project_name', 'description', 'images', 'ai_descriptions', 'overall_description', 'api_documentation'];

    protected $casts = [
        'images' => 'array',
        'ai_descriptions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
