<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matiere extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'code',
        'coefficient',
        'niveau',
        'enseignant_id',
    ];

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
        ];
    }

    // Relations
    public function enseignant()
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    // Scopes
    public function scopeParNiveau($query, $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    public function scopeParEnseignant($query, $enseignantId)
    {
        return $query->where('enseignant_id', $enseignantId);
    }

    // Accessors
    public function getNomCompletAttribute()
    {
        return $this->nom . ' (' . $this->code . ')';
    }

    public function getNomEnseignantAttribute()
    {
        return $this->enseignant->nom_complet ?? 'Non assigné';
    }

    // Méthodes utiles
    public function getMoyenneClasse($classeId, $periode = null)
    {
        $query = $this->notes()->where('classe_id', $classeId);
        
        if ($periode) {
            $query->where('periode', $periode);
        }
        
        return $query->avg('valeur');
    }
}