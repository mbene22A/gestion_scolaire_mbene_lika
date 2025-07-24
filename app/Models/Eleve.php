<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eleve extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricule_eleve',
        'date_naissance',
        'adresse',
        'telephone_parent',
        'email_parent',
        'classe_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
        ];
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function bulletins()
    {
        return $this->hasMany(Bulletin::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    // Accessors
    public function getNomCompletAttribute()
    {
        return $this->user->full_name;
    }

    public function getAgeAttribute()
    {
        return $this->date_naissance->age;
    }

    // Méthodes utiles
    public function getMoyenneGenerale($periode = null)
    {
        $query = $this->notes();
        
        if ($periode) {
            $query->where('periode', $periode);
        }
        
        return $query->avg('valeur');
    }

    public function getNotesParMatiere($periode = null)
    {
        $query = $this->notes()->with('matiere');
        
        if ($periode) {
            $query->where('periode', $periode);
        }
        
        return $query->get()->groupBy('matiere.nom');
    }
}