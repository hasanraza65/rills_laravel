<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QbTopicObjective extends Model
{
    protected $fillable = [
        'topic_id',
        'objective',
    ];

    public function topic()
    {
        return $this->belongsTo(QbTopic::class, 'topic_id');
    }
}