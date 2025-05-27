<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:super-admin']);
    }

    /**
     * Display the system settings.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $settings = $this->getSettings();
        
        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Update the system settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_name' => 'required|string|max:255',
            'app_description' => 'nullable|string',
            'company_name' => 'required|string|max:255',
            'company_address' => 'required|string',
            'company_email' => 'required|email',
            'company_phone' => 'required|string|max:20',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png|max:1024',
            'timezone' => 'required|string',
            'date_format' => 'required|string',
            'time_format' => 'required|string',
            'default_language' => 'required|string|in:en,sw',
            'currency_code' => 'required|string|max:3',
            'currency_symbol' => 'required|string|max:5',
            'mail_driver' => 'required|string',
            'mail_host' => 'required_if:mail_driver,smtp|nullable|string',
            'mail_port' => 'required_if:mail_driver,smtp|nullable|integer',
            'mail_username' => 'required_if:mail_driver,smtp|nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old settings for audit log
        $oldSettings = $this->getSettings();

        // Get .env file contents
        $envFile = base_path('.env');
        $envContents = File::get($envFile);

        // Update .env file with new settings
        $envContents = $this->updateEnvVariable($envContents, 'APP_NAME', '"' . $request->app_name . '"');
        $envContents = $this->updateEnvVariable($envContents, 'COMPANY_NAME', '"' . $request->company_name . '"');
        $envContents = $this->updateEnvVariable($envContents, 'COMPANY_ADDRESS', '"' . $request->company_address . '"');
        $envContents = $this->updateEnvVariable($envContents, 'COMPANY_EMAIL', $request->company_email);
        $envContents = $this->updateEnvVariable($envContents, 'COMPANY_PHONE', $request->company_phone);
        $envContents = $this->updateEnvVariable($envContents, 'APP_TIMEZONE', $request->timezone);
        $envContents = $this->updateEnvVariable($envContents, 'APP_DATE_FORMAT', $request->date_format);
        $envContents = $this->updateEnvVariable($envContents, 'APP_TIME_FORMAT', $request->time_format);
        $envContents = $this->updateEnvVariable($envContents, 'APP_LOCALE', $request->default_language);
        $envContents = $this->updateEnvVariable($envContents, 'CURRENCY_CODE', $request->currency_code);
        $envContents = $this->updateEnvVariable($envContents, 'CURRENCY_SYMBOL', $request->currency_symbol);
        
        // Mail settings
        $envContents = $this->updateEnvVariable($envContents, 'MAIL_MAILER', $request->mail_driver);
        $envContents = $this->updateEnvVariable($envContents, 'MAIL_HOST', $request->mail_host);
        $envContents = $this->updateEnvVariable($envContents, 'MAIL_PORT', $request->mail_port);
        $envContents = $this->updateEnvVariable($envContents, 'MAIL_USERNAME', $request->mail_username);
        
        // Only update password if provided
        if ($request->filled('mail_password')) {
            $envContents = $this->updateEnvVariable($envContents, 'MAIL_PASSWORD', $request->mail_password);
        }
        
        $envContents = $this->updateEnvVariable($envContents, 'MAIL_ENCRYPTION', $request->mail_encryption);
        $envContents = $this->updateEnvVariable($envContents, 'MAIL_FROM_ADDRESS', $request->mail_from_address);
        $envContents = $this->updateEnvVariable($envContents, 'MAIL_FROM_NAME', '"' . $request->mail_from_name . '"');
        
        // Save changes to .env file
        File::put($envFile, $envContents);

        // Handle logo upload
        if ($request->hasFile('company_logo')) {
            // Delete old logo if exists
            if (File::exists(public_path('images/logo.png'))) {
                File::delete(public_path('images/logo.png'));
            }
            
            // Save new logo
            $request->file('company_logo')->move(public_path('images'), 'logo.png');
        }
        
        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            // Delete old favicon if exists
            if (File::exists(public_path('favicon.ico'))) {
                File::delete(public_path('favicon.ico'));
            }
            
            // Save new favicon
            $request->file('favicon')->move(public_path(), 'favicon.ico');
        }

        // Save app description
        $configFile = config_path('app.php');
        $configContents = File::get($configFile);
        $configContents = preg_replace(
            "/'description' => '.*?'/",
            "'description' => '" . addslashes($request->app_description) . "'",
            $configContents
        );
        File::put($configFile, $configContents);

        // Clear cache
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE_SETTINGS',
            'model_type' => 'Settings',
            'model_id' => 0,
            'description' => 'System settings updated by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldSettings),
            'new_values' => json_encode($this->getSettings()),
        ]);

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings updated successfully. Application may need to be restarted for some changes to take effect.');
    }

    /**
     * Display the language settings.
     *
     * @return \Illuminate\View\View
     */
    public function languages()
    {
        $languages = [
            'en' => 'English',
            'sw' => 'Swahili',
        ];
        
        $translations = Translation::all()->groupBy('locale');
        
        return view('admin.settings.languages', compact('languages', 'translations'));
    }

    /**
     * Display the translation form for a specific language.
     *
     * @param  string  $locale
     * @return \Illuminate\View\View
     */
    public function showTranslations($locale)
    {
        if (!in_array($locale, ['en', 'sw'])) {
            return redirect()->route('admin.settings.languages')
                ->with('error', 'Invalid language selected.');
        }
        
        $translations = Translation::where('locale', $locale)->get()->keyBy('key');
        
        // Get English translations as reference
        $englishTranslations = Translation::where('locale', 'en')->get()->keyBy('key');
        
        return view('admin.settings.translations', compact('locale', 'translations', 'englishTranslations'));
    }

    /**
     * Update translations for a specific language.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateTranslations(Request $request, $locale)
    {
        if (!in_array($locale, ['en', 'sw'])) {
            return redirect()->route('admin.settings.languages')
                ->with('error', 'Invalid language selected.');
        }
        
        $translations = $request->translations;
        
        if (is_array($translations)) {
            foreach ($translations as $key => $value) {
                Translation::updateOrCreate(
                    ['locale' => $locale, 'key' => $key],
                    ['value' => $value]
                );
            }
        }
        
        // Clear cache
        Cache::forget('translations.' . $locale);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE_TRANSLATIONS',
            'model_type' => 'Translation',
            'model_id' => 0,
            'description' => "Updated translations for {$locale} language",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return redirect()->route('admin.settings.languages')
            ->with('success', 'Translations updated successfully.');
    }

    /**
     * Display the backup settings.
     *
     * @return \Illuminate\View\View
     */
    public function backups()
    {
        $backupPath = storage_path('app/backups');
        $backups = [];
        
        if (File::exists($backupPath)) {
            $files = File::files($backupPath);
            
            foreach ($files as $file) {
                $backups[] = [
                    'name' => $file->getFilename(),
                    'size' => $this->formatBytes($file->getSize()),
                    'date' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }
        
        return view('admin.settings.backups', compact('backups'));
    }

    /**
     * Create a new database backup.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createBackup(Request $request)
    {
        try {
            // Create backups directory if it doesn't exist
            $backupPath = storage_path('app/backups');
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }
            
            // Generate backup filename
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $outputFile = $backupPath . '/' . $filename;
            
            // Get database config
            $host = config('database.connections.mysql.host');
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            
            // Create backup command
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($database),
                escapeshellarg($outputFile)
            );
            
            // Execute command
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                return back()->with('error', 'Failed to create backup. Error code: ' . $returnVar);
            }
            
            // Log action
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'CREATE_BACKUP',
                'model_type' => 'Backup',
                'model_id' => 0,
                'description' => "Created database backup: {$filename}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->with('success', 'Backup created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create backup: ' . $e->getMessage());
        }
    }

    /**
     * Download a backup file.
     *
     * @param  string  $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadBackup($filename)
    {
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (!File::exists($backupPath)) {
            return back()->with('error', 'Backup file not found.');
        }
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DOWNLOAD_BACKUP',
            'model_type' => 'Backup',
            'model_id' => 0,
            'description' => "Downloaded database backup: {$filename}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        return response()->download($backupPath);
    }

    /**
     * Delete a backup file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $filename
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteBackup(Request $request, $filename)
    {
        $backupPath = storage_path('app/backups/' . $filename);
        
        if (!File::exists($backupPath)) {
            return back()->with('error', 'Backup file not found.');
        }
        
        File::delete($backupPath);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE_BACKUP',
            'model_type' => 'Backup',
            'model_id' => 0,
            'description' => "Deleted database backup: {$filename}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return back()->with('success', 'Backup deleted successfully.');
    }

    /**
     * Display the system logs.
     *
     * @return \Illuminate\View\View
     */
    public function logs()
    {
        $logs = AuditLog::with('user')->orderBy('created_at', 'desc')->paginate(20);
        
        return view('admin.settings.logs', compact('logs'));
    }

    /**
     * Clear the system logs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearLogs(Request $request)
    {
        // Only clear logs older than 30 days
        $date = now()->subDays(30);
        AuditLog::where('created_at', '<', $date)->delete();
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CLEAR_LOGS',
            'model_type' => 'AuditLog',
            'model_id' => 0,
            'description' => 'Cleared system logs older than 30 days',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return back()->with('success', 'Logs older than 30 days cleared successfully.');
    }

    /**
     * Get all system settings.
     *
     * @return array
     */
    private function getSettings()
    {
        return [
            'app_name' => config('app.name'),
            'app_description' => config('app.description'),
            'company_name' => env('COMPANY_NAME'),
            'company_address' => env('COMPANY_ADDRESS'),
            'company_email' => env('COMPANY_EMAIL'),
            'company_phone' => env('COMPANY_PHONE'),
            'timezone' => config('app.timezone'),
            'date_format' => env('APP_DATE_FORMAT', 'Y-m-d'),
            'time_format' => env('APP_TIME_FORMAT', 'H:i:s'),
            'default_language' => config('app.locale'),
            'currency_code' => env('CURRENCY_CODE', 'KES'),
            'currency_symbol' => env('CURRENCY_SYMBOL', 'Ksh'),
            'mail_driver' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => config('mail.mailers.smtp.port'),
            'mail_username' => config('mail.mailers.smtp.username'),
            'mail_encryption' => config('mail.mailers.smtp.encryption'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
        ];
    }

    /**
     * Update a variable in the .env file.
     *
     * @param  string  $envContents
     * @param  string  $key
     * @param  string  $value
     * @return string
     */
    private function updateEnvVariable($envContents, $key, $value)
    {
        if (str_contains($envContents, $key . '=')) {
            return preg_replace("/{$key}=(.*)/", "{$key}={$value}", $envContents);
        } else {
            return $envContents . "\n{$key}={$value}\n";
        }
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param  int  $bytes
     * @param  int  $precision
     * @return string
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
