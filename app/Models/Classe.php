<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'niveau',
        'annee_scolaire',
        'enseignant_principal_id',
    ];

    // Relations
    public function enseignantPrincipal()
    {
        return $this->belongsTo(User::class, 'enseignant_principal_id');
    }

    public function eleves()
    {
        return $this->hasMany(Eleve::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function bulletins()
    {
        return $this->hasManyThrough(Bulletin::class, Eleve::class);
    }

    // Scopes
    public function scopeParNiveau($query, $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    public function scopeParAnnee($query, $annee)
    {
        return $query->where('annee_scolaire', $annee);
    }

    // Accessors
    public function getNomCompletAttribute()
    {
        return $this->nom . ' - ' . $this->niveau . ' (' . $this->annee_scolaire . ')';
    }

    public function getNombreElevesAttribute()
    {
        return $this->eleves()->count();
    }
}