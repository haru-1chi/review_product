<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
class product extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['name', 'category', 'description', 'price', 'picture'];

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function getPictureUrlAttribute()
    {
        $picturePath = $this->attributes['picture'];
        $appUrl = Config::get('app.url');
        return $appUrl . Storage::url($picturePath);
    }
}
