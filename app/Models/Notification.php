<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'message',
        'type',
        'priorite',
        'lu',
        'date_lecture',
        'donnees_supplementaires',
        'lien_action',
        'user_id',
        'envoye_par',
    ];

    protected function casts(): array
    {
        return [
            'lu' => 'boolean',
            'date_lecture' => 'datetime',
            'donnees_supplementaires' => 'array',
        ];
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function envoyePar()
    {
        return $this->belongsTo(User::class, 'envoye_par');
    }

    // Scopes
    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }

    public function scopeLues($query)
    {
        return $query->where('lu', true);
    }

    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeParPriorite($query, $priorite)
    {
        return $query->where('priorite', $priorite);
    }

    public function scopeUrgentes($query)
    {
        return $query->where('priorite', 'urgente');
    }

    public function scopeRecentes($query, $jours = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($jours));
    }

    // Accessors
    public function getTypeLibelleAttribute()
    {
        return match($this->type) {
            'info' => 'Information',
            'bulletin' => 'Bulletin',
            'document' => 'Document',
            'urgent' => 'Urgent',
            'inscription' => 'Inscription',
            'note' => 'Note',
            default => $this->type
        };
    }

    public function getPrioriteLibelleAttribute()
    {
        return match($this->priorite) {
            'basse' => 'Basse',
            'normale' => 'Normale',
            'haute' => 'Haute',
            'urgente' => 'Urgente',
            default => $this->priorite
        };
    }

    public function getPrioriteCouleurAttribute()
    {
        return match($this->priorite) {
            'basse' => '#6b7280',
            'normale' => '#3b82f6',
            'haute' => '#f59e0b',
            'urgente' => '#ef4444',
            default => '#6b7280'
        };
    }

    // Méthodes utiles
    public function marquerCommeLue()
    {
        $this->update([
            'lu' => true,
            'date_lecture' => now(),
        ]);
    }

    public static function creerNotification($userId, $titre, $message, $type = 'info', $priorite = 'normale', $envoyePar = null, $donneesSupp = null, $lienAction = null)
    {
        return self::create([
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'priorite' => $priorite,
            'user_id' => $userId,
            'envoye_par' => $envoyePar,
            'donnees_supplementaires' => $donneesSupp,
            'lien_action' => $lienAction,
        ]);
    }
}