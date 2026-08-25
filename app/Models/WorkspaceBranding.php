<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceBranding extends Model
{
    protected $fillable = ['workspace_id', 'logo_path', 'primary_colour', 'accent_colour', 'email_footer'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
