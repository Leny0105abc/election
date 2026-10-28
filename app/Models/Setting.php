<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value settings (report signatories, election banner text, ...).
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('schoolvote.settings', 3600, fn () => static::pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('schoolvote.settings');
    }

    /**
     * Report signature blocks: list of ['label' => 'Prepared by', 'name' => ..., 'title' => ...].
     *
     * @return list<array{label: string, name: string, title: string}>
     */
    public static function signatories(): array
    {
        return static::get('report.signatories', [
            ['label' => 'Prepared by', 'name' => '', 'title' => 'SSLG Adviser'],
            ['label' => 'Certified by', 'name' => '', 'title' => 'Election Committee Chairperson'],
            ['label' => 'Noted by', 'name' => '', 'title' => 'School Principal'],
        ]);
    }
}
