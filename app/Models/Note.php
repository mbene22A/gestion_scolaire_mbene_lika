<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'valeur',
        'periode',
        'date_note',
        'type_evaluation',
        'commentaire',
        'eleve_id',
        'matiere_id',
        'classe_id',
    ];

    protected function casts(): array
    {
        return [
            'valeur' => 'decimal:2',
            'date_note' => 'date',
        ];
    }

    // Relations
    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function matiere()
    {
        return $this->belongsTo(Matiere::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    // Scopes
    public function scopeParPeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }

    public function scopeParEleve($query, $eleveId)
    {
        return $query->where('eleve_id', $eleveId);
    }

    public function scopeParMatiere($query, $matiereId)
    {
        return $query->where('matiere_id', $matiereId);
    }

    public function scopeParClasse($query, $classeId)
    {
        return $query->where('classe_id', $classeId);
    }

    // Accessors
    public function getValeurFormateeAttribute()
    {
        return number_format($this->valeur, 2) . '/20';
    }

    public function getAppreciationNoteAttribute()
    {
        if ($this->valeur >= 16) return 'Excellent';
        if ($this->valeur >= 14) return 'Très bien';
        if ($this->valeur >= 12) return 'Bien';
        if ($this->valeur >= 10) return 'Assez bien';
        if ($this->valeur >= 8) return 'Passable';
        return 'Insuffisant';
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

    public function getTypeEvaluationLibelleAttribute()
    {
        return match($this->type_evaluation) {
            'devoir' => 'Devoir',
            'composition' => 'Composition',
            'interrogation' => 'Interrogation',
            'examen' => 'Examen',
            default => ucfirst($this->type_evaluation)
        };
    }
}