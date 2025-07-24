<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    use HasFactory;

    protected $fillable = [
        'periode',
        'annee_scolaire',
        'moyenne_generale',
        'mention',
        'rang',
        'total_eleves',
        'chemin_pdf',
        'publie',
        'appreciation',
        'eleve_id',
    ];

    protected function casts(): array
    {
        return [
            'moyenne_generale' => 'decimal:2',
            'publie' => 'boolean',
        ];
    }

    // Relations
    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    // Scopes
    public function scopePublies($query)
    {
        return $query->where('publie', true);
    }

    public function scopeParPeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }

    public function scopeParAnnee($query, $annee)
    {
        return $query->where('annee_scolaire', $annee);
    }

    // Accessors
    public function getMoyenneFormateeAttribute()
    {
        return number_format($this->moyenne_generale, 2) . '/20';
    }

    public function getRangFormatAttribute()
    {
        return $this->rang . '/' . $this->total_eleves;
    }

    public function getPeriodeLibelleAttribute()
    {
        return match($this->periode) {
            'trimestre_1' => '1er Trimestre',
            'trimestre_2' => '2ème Trimestre',
            'trimestre_3' => '3ème Trimestre',
            default => $this->periode
        };
    }

    // Méthodes utiles
    public function getNotesDetaillees()
    {
        return $this->eleve->notes()
            ->where('periode', $this->periode)
            ->with('matiere')
            ->get()
            ->groupBy('matiere.nom');
    }

    public function genererPdf()
    {
        // À implémenter pour la génération PDF
        return null;
    }
}