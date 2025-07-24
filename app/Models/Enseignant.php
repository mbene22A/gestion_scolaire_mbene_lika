<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    use HasFactory;

    protected $fillable = [
        'telephone',
        'specialite',
        'user_id',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function matieres()
    {
        return $this->hasMany(Matiere::class);
    }

    public function classesPrincipales()
    {
        return $this->hasMany(Classe::class, 'enseignant_principal_id', 'user_id');
    }

    // Accessors
    public function getNomCompletAttribute()
    {
        return $this->user->full_name;
    }

    public function getEmailAttribute()
    {
        return $this->user->email;
    }

    // Méthodes utiles
    public function getClassesEnseignees()
    {
        return Classe::whereHas('notes.matiere', function($query) {
            $query->where('enseignant_id', $this->id);
        })->distinct()->get();
    }

    public function getElevesEnseignes()
    {
        return Eleve::whereHas('notes.matiere', function($query) {
            $query->where('enseignant_id', $this->id);
        })->distinct()->with('user')->get();
    }
}