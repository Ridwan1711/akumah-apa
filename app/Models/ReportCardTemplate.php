<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportCardTemplate extends Model
{
    protected $fillable = [
        'name',
        'is_default',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'config' => 'array',
        ];
    }

    public const EDITOR_BLOCK = 'block';
    public const EDITOR_CANVA = 'canva';

    public static function defaultConfig(): array
    {
        return [
            'editor_type' => self::EDITOR_BLOCK,
            'layout' => ['header', 'info', 'grades', 'tahfidz', 'violations', 'notes', 'footer', 'qr'],
            'blocks' => [
                'header' => ['visible' => true],
                'info' => ['visible' => true],
                'grades' => ['visible' => true],
                'tahfidz' => ['visible' => true],
                'violations' => ['visible' => true],
                'notes' => ['visible' => true],
                'footer' => ['visible' => true],
                'qr' => ['visible' => true, 'position' => 'bottom-right'],
            ],
            'style' => [
                'font_family' => 'DejaVu Sans',
                'font_size' => 11,
                'primary_color' => '#1a1a1a',
                'header_bg' => '#f0f0f0',
                'header_border_color' => '#333',
            ],
            'images' => [
                'logo' => null,
                'signature_wali' => null,
                'signature_kepala' => null,
                'stamp' => null,
            ],
            'canva' => [
                'html' => null,
                'css' => null,
            ],
        ];
    }

    public function isCanva(): bool
    {
        return ($this->config['editor_type'] ?? self::EDITOR_BLOCK) === self::EDITOR_CANVA;
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public static function getActive(): ?self
    {
        $template = static::default()->first();
        if ($template) {
            return $template;
        }

        return static::first();
    }
}
