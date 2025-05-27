<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Download the specified document.
     *
     * @param  \App\Models\Document  $document
     * @return \Illuminate\Http\Response
     */
    public function download(Document $document)
    {
        // Check if user has permission to download this document
        if (!auth()->user()->can('view', $document)) {
            abort(403, 'Unauthorized action.');
        }
        
        return Storage::download($document->file_path, $document->original_filename);
    }
} 