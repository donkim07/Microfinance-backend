<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployeeDetail;
use App\Models\Role;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:15', 'unique:users'],
            'employee_number' => ['required', 'string', 'max:20', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'institution_id' => ['required', 'exists:institutions,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'designation_id' => ['required', 'exists:designations,id'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'employee_number' => $data['employee_number'],
            'password' => Hash::make($data['password']),
        ]);

        // Assign default user role
        $defaultRole = Role::where('slug', 'user')->first();
        if ($defaultRole) {
            $user->roles()->attach($defaultRole->id);
        }

        // Create employee details
        EmployeeDetail::create([
            'user_id' => $user->id,
            'institution_id' => $data['institution_id'],
            'department_id' => $data['department_id'],
            'designation_id' => $data['designation_id'],
            'employment_date' => now(),
            'status' => 'ACTIVE',
        ]);

        return $user;
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        event(new Registered($user = $this->create($request->all())));

        // Log registration action
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'REGISTER',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => 'User registered successfully',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $user->name,
                'email' => $user->email,
                'employee_number' => $user->employee_number,
                'phone_number' => $user->phone_number,
            ]),
        ]);

        $this->guard()->login($user);

        if ($response = $this->registered($request, $user)) {
            return $response;
        }

        return $request->wantsJson()
            ? new JsonResponse([], 201)
            : redirect($this->redirectPath());
    }

    /**
     * Get the post register redirect path.
     *
     * @return string
     */
    public function redirectPath()
    {
        return $this->redirectTo;
    }
}
