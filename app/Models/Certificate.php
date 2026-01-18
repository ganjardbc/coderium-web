<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'track_id',
        'certificate_number',
        'title',
        'description',
        'issued_at',
        'completed_at',
        'metadata',
        'verification_url',
        'download_count',
        'downloaded_at',
        'is_valid',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'completed_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'metadata' => 'array',
        'download_count' => 'integer',
        'is_valid' => 'boolean',
    ];

    /**
     * The user who earned the certificate.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The track for which the certificate was issued.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Generate a unique certificate number.
     */
    public static function generateCertificateNumber(): string
    {
        do {
            $number = 'CERT-' . strtoupper(Str::random(8));
        } while (self::where('certificate_number', $number)->exists());

        return $number;
    }

    /**
     * Generate a verification URL for this certificate.
     */
    public function generateVerificationUrl(): string
    {
        return url("/certificates/verify/{$this->certificate_number}");
    }
}
