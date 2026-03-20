<?php

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'format',
        'status',
        'name',
        'description',
        'filters',
        'parameters',
        'total_records',
        'processed_records',
        'progress',
        'file_path',
        'file_name',
        'file_size',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'type' => ReportType::class,
        'format' => ReportFormat::class,
        'status' => ReportStatus::class,
        'filters' => 'array',
        'parameters' => 'array',
        'progress' => 'integer',
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['download_url', 'file_size_formatted'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Models\ReportNotification::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('status', ReportStatus::PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', ReportStatus::PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ReportStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', ReportStatus::FAILED);
    }

    public function scopeOfType($query, ReportType $type)
    {
        return $query->where('type', $type);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => ReportStatus::PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $filePath, string $fileName, int $fileSize): void
    {
        $this->update([
            'status' => ReportStatus::COMPLETED,
            'progress' => 100,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => ReportStatus::FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function updateProgress(int $processed, int $total): void
    {
        $progress = $total > 0 ? (int) round(($processed / $total) * 100) : 0;

        $this->update([
            'processed_records' => $processed,
            'total_records' => $total,
            'progress' => min($progress, 100),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => ReportStatus::CANCELLED,
            'completed_at' => now(),
        ]);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path || $this->status !== ReportStatus::COMPLETED) {
            return null;
        }

        return "/api/reports/{$this->id}/download";
    }

    public function getFileSizeFormattedAttribute(): ?string
    {
        if (!$this->file_size) {
            return null;
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $seconds = $this->completed_at->diffInSeconds($this->started_at);

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return round($seconds / 60) . 'm';
        }

        return round($seconds / 3600, 1) . 'h';
    }

    public function getFullFilePath(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::disk('local')->path($this->file_path);
    }

    public function deleteFile(): void
    {
        if ($this->file_path && Storage::disk('local')->exists($this->file_path)) {
            Storage::disk('local')->delete($this->file_path);
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (Report $report) {
            $report->deleteFile();
        });
    }
}
