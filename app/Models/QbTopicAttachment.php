<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QbTopicAttachment extends Model
{
    protected $fillable = [
        'topic_id',
        'file_path',
        'file_name',
    ];

    public function topic()
    {
        return $this->belongsTo(QbTopic::class, 'topic_id');
    }
}