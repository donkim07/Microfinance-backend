<?php

namespace App\Http\Controllers;

use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    /**
     * Display a listing of the translations.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $translations = Translation::orderBy('key')->paginate(20);
        return view('admin.languages.index', compact('translations'));
    }

    /**
     * Show the form for creating a new translation.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.languages.create');
    }

    /**
     * Store a newly created translation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255|unique:translations',
            'en' => 'required|string',
            'sw' => 'required|string',
        ]);

        Translation::create([
            'key' => $request->key,
            'en' => $request->en,
            'sw' => $request->sw,
        ]);

        return redirect()->route('admin.languages.index')
            ->with('success', 'Translation created successfully.');
    }

    /**
     * Show the form for editing the specified translation.
     *
     * @param  \App\Models\Translation  $translation
     * @return \Illuminate\Http\Response
     */
    public function edit(Translation $language)
    {
        return view('admin.languages.edit', compact('language'));
    }

    /**
     * Update the specified translation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Translation  $translation
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Translation $language)
    {
        $request->validate([
            'en' => 'required|string',
            'sw' => 'required|string',
        ]);

        $language->update([
            'en' => $request->en,
            'sw' => $request->sw,
        ]);

        return redirect()->route('admin.languages.index')
            ->with('success', 'Translation updated successfully.');
    }

    /**
     * Remove the specified translation from storage.
     *
     * @param  \App\Models\Translation  $translation
     * @return \Illuminate\Http\Response
     */
    public function destroy(Translation $language)
    {
        $language->delete();

        return redirect()->route('admin.languages.index')
            ->with('success', 'Translation deleted successfully.');
    }

    /**
     * Change the application language.
     *
     * @param  string  $locale
     * @return \Illuminate\Http\Response
     */
    public function changeLanguage($locale)
    {
        if (!in_array($locale, ['en', 'sw'])) {
            abort(400, 'Invalid locale');
        }

        Session::put('locale', $locale);
        App::setLocale($locale);
        
        // Update user preference if authenticated
        if (auth()->check()) {
            auth()->user()->update(['preferred_language' => $locale]);
        }

        return redirect()->back();
    }
}
