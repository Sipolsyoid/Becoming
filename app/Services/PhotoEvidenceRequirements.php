<?php

namespace App\Services;

class PhotoEvidenceRequirements
{
    public static function hasUnverifiableMeasurement(string $habit): bool
    {
        // Explicit English/Latvian measurements describe totals or elapsed activity,
        // not facts that one still image can establish. This is deliberately conservative.
        $amount = '(?:\d+(?:[.,]\d+)?|a|an|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|half|vienu|viena|viens|divus|divas|divi|trīs|četrus|četras|četri|piecus|piecas|pieci|sešus|sešas|seši|septiņus|septiņas|septiņi|astoņus|astoņas|astoņi|deviņus|deviņas|deviņi|desmit)';
        $units = '(?:seconds?|secs?|minutes?|mins?|hours?|hrs?|days?|weeks?|months?|km|kilomet(?:er|re)s?|miles?|met(?:er|re)s?|lit(?:er|re)s?|ml|steps?|repetitions?|reps?|push[ -]?ups?|pages?|sekund(?:e|es|i|ēm|es)?|minūt(?:e|es|i|ēm|es)?|stund(?:a|as|u|ām)?|dien(?:a|as|u|ām)?|nedēļ(?:a|as|u|ām)?|kilometr(?:s|i|us|u|iem)?|metr(?:s|i|us|u|iem)?|litr(?:s|i|us|u|iem)?|soļ(?:i|us|u|iem))';

        return preg_match('/(*UCP)\b'.$amount.'\s*'.$units.'\b/iu', $habit) === 1
            || preg_match('/(*UCP)\b(?:all|whole|entire)\s+(?:day|night)|\bvisu\s+(?:dienu|nakti)\b/iu', $habit) === 1;
    }
}
