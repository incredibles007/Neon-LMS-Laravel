<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class Sponsor extends Model
{
    protected $guarded = [];
    protected $appends = ['image'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($sponsor) { // before delete() method call this
            if (File::exists(public_path('/storage/uploads/' . $sponsor->logo))) {
                File::delete(public_path('/storage/uploads/' . $sponsor->logo));
            }
        });
    }

    public function getImageAttribute()
    {
        if ($this->logo != null) {
            return url('storage/uploads/'.$this->logo);
        }
        return NULL;
    }

}
