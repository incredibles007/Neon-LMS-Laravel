<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class Slider extends Model
{
    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($slider) { // before delete() method call this
            if (File::exists(public_path('/storage/uploads/' . $slider->bg_image))) {
                File::delete(public_path('/storage/uploads/' . $slider->bg_image));
            }
        });
    }
}
