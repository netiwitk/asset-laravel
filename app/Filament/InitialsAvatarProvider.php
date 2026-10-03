<?php

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Draws a first-letter avatar as an inline SVG, so no user name is sent to
 * an outside service (Filament's default fetches from ui-avatars.com).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    /**
     * Soft background / text pairs; a user keeps the same pair via their id.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const TINTS = [
        ['#dbeafe', '#1d4ed8'],
        ['#cffafe', '#0e7490'],
        ['#ede9fe', '#6d28d9'],
        ['#fef3c7', '#b45309'],
        ['#d1fae5', '#047857'],
        ['#ffe4e6', '#be123c'],
        ['#e0e7ff', '#4338ca'],
    ];

    public function get(Model $record): string
    {
        [$background, $text] = self::TINTS[(int) $record->getKey() % count(self::TINTS)];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            ."<rect width=\"64\" height=\"64\" fill=\"{$background}\"/>"
            ."<text x=\"32\" y=\"32\" dy=\".35em\" text-anchor=\"middle\" fill=\"{$text}\" font-size=\"28\" font-weight=\"600\" font-family=\"IBM Plex Sans Thai, Thonburi, Noto Sans Thai, sans-serif\">"
            .e(self::initial(Filament::getNameForDefaultAvatar($record)))
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * First letter of the name. Thai vowels เ แ โ ใ ไ are written before their consonant,
     * so they are skipped: "เอกชัย" gives "อ", not "เ".
     */
    public static function initial(string $name): string
    {
        $letters = preg_replace('/^[^\p{L}\p{N}]*[เแโใไ]?/u', '', trim($name));

        return filled($letters) ? mb_strtoupper(mb_substr($letters, 0, 1)) : '?';
    }
}
