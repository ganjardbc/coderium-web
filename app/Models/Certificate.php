<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'track_id', // Maintained for backward compatibility
        'certifiable_type', // New polymorphic type field
        'certifiable_id', // New polymorphic id field
        'template_id',
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
     * Maintained for backward compatibility.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Get the certifiable entity (track or course).
     * New polymorphic relationship for dynamic certificate support.
     */
    public function certifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the certificate template used for this certificate.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
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

    /**
     * Get the certifiable entity with backward compatibility.
     * Returns track if polymorphic fields are not set.
     */
    public function getCertifiableEntityAttribute()
    {
        if ($this->certifiable_type && $this->certifiable_id) {
            return $this->certifiable;
        }

        // Fallback to track for backward compatibility
        return $this->track;
    }

    /**
     * Get the certificate type (track or course) for display purposes.
     */
    public function getCertificateTypeAttribute(): string
    {
        if ($this->certifiable_type) {
            return class_basename($this->certifiable_type);
        }

        // Fallback to track for backward compatibility
        return 'Track';
    }

    /**
     * Check if this is a track certificate (backward compatibility).
     */
    public function isTrackCertificate(): bool
    {
        return $this->certifiable_type === 'App\\Models\\Track' ||
               (!$this->certifiable_type && $this->track_id);
    }

    /**
     * Check if this is a course certificate.
     */
    public function isCourseCertificate(): bool
    {
        return $this->certifiable_type === 'App\\Models\\Course';
    }
}
