<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteForm extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'site_memo' => [
            'title' => 'Site Memo',
            'doc_no' => 'MGE/FORM/GF/4',
            'revision' => 1,
            'effective_date' => '01 September 2018',
            'ref_prefix' => 'SM',
        ],
        'engineering_instruction' => [
            'title' => 'Arahan Kejuruteraan (Engineering Instruction - EI)',
            'doc_no' => 'MGE/FORM/GF/5',
            'revision' => 1,
            'effective_date' => '17 September 2026',
            'ref_prefix' => 'EI',
        ],
        'permit_to_work' => [
            'title' => 'Permit Untuk Bekerja (Permit To Work - PTW)',
            'doc_no' => 'MGE/FORM/GF/7',
            'revision' => 0,
            'effective_date' => '17 September 2026',
            'ref_prefix' => 'PTW',
        ],
        'daily_site_diary' => [
            'title' => 'Borang Harian Tapak Bina (BHTB) / Daily Site Diary',
            'doc_no' => 'MGE-QF-01',
            'revision' => 0,
            'effective_date' => '17 Sept 2026',
            'ref_prefix' => 'BHTB',
        ],
        'material_approval' => [
            'title' => 'Borang Kelulusan Bahan / Request for Approval (RFA)',
            'doc_no' => 'MGE-QF-02',
            'revision' => 0,
            'effective_date' => '17 Sept 2026',
            'ref_prefix' => 'RFA',
        ],
        'ncr' => [
            'title' => 'Laporan Ketidakakuran (Non-Conformance Report - NCR)',
            'doc_no' => 'MGE-QF-03',
            'revision' => 0,
            'effective_date' => '17 Sept 2026',
            'ref_prefix' => 'NCR',
        ],
        'rfi' => [
            'title' => 'Permohonan Maklumat (Request For Information - RFI)',
            'doc_no' => 'MGE-QF-04',
            'revision' => 0,
            'effective_date' => '17 Sept 2026',
            'ref_prefix' => 'RFI',
        ],
        'rfwi' => [
            'title' => 'Permohonan Pemeriksaan Kerja (Request For Work Inspection - RFWI)',
            'doc_no' => 'MGE-QF-05',
            'revision' => 0,
            'effective_date' => '17 Sept 2026',
            'ref_prefix' => 'RFWI',
        ],
    ];

    protected $fillable = [
        'form_type',
        'project_id',
        'ref_no',
        'form_date',
        'title',
        'status',
        'data',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'form_date' => 'date',
        ];
    }

    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SiteFormAttachment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('form_type', $type);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(fn ($q) => $q->where('ref_no', 'like', "%{$term}%")
            ->orWhere('title', 'like', "%{$term}%"));
    }
}
