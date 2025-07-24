<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_fichier',
        'nom_original',
        'chemin_fichier',
        'type_fichier',
        'mime_type',
        'taille_fichier',
        'type_document',
        'obligatoire',
        'valide',
        'description',
        'eleve_id',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'obligatoire' => 'boolean',
            'valide' => 'boolean',
            'taille_fichier' => 'integer',
        ];
    }

    // Relations
    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scopes
    public function scopeObligatoires($query)
    {
        return $query->where('obligatoire', true);
    }

    public function scopeValides($query)
    {
        return $query->where('valide', true);
    }

    public function scopeParType($query, $type)
    {
        return $query->where('type_document', $type);
    }

    // Accessors
    public function getTailleFormateeAttribute()
    {
        $bytes = $this->taille_fichier;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }

    public function getTypeLibelleAttribute()
    {
        return match($this->type_document) {
            'certificat_scolarite' => 'Certificat de scolarité',
            'bulletin' => 'Bulletin',
            'justificatif' => 'Justificatif',
            'autre' => 'Autre',
            default => $this->type_document
        };
    }

    public function getUrlTelechargementAttribute()
    {
        return route('api.documents.download', $this->id);
    }
}