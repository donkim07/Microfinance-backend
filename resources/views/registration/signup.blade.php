<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite('resources/css/app.css')

    </head>
<body>


        <div class="flex h-screen items-center justify-center bg-gradient-to-r from-green-200 via-blue-200 to-purple-200">
    <div class="relative w-full max-w-sm sm:max-w-md p-14 bg-white bg-opacity-25 backdrop-blur-md rounded-lg shadow-lg">
      <div class="flex flex-col items-center">
        <div class="flex items-center justify-center w-12 h-12 mb-4 bg-gradient-to-r from-purple-400 via-blue-300 to-green-500 rounded-full">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            class="w-6 h-6 text-white"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="2"
              d="M12 14l9-5-9-5-9 5zm0 7l7-4-7-4-7 4 7 4z"
            />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-4">Sign Up</h2>
      </div>
      <form onSubmit={handleSubmit} class="flex flex-col gap-4">
        {{-- {errorMessage && ( --}}
          <div class="text-red-500 text-sm mb-2">
            {{-- {errorMessage} --}}
        </div>
        {{-- )} --}}
        <div>
          <label class="block text-gray-700" htmlFor="firstname">
            First Name
          </label>
          <input
            type="text"
            id="firstname"
            name={firstname}
            {{-- onChange={(e) => setFirstname(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-gray-700" htmlFor="surename">
            SurName
          </label>
          <input
            type="text"
            id="surename"
            name={surname}
            {{-- onChange={(e) => setSurname(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-gray-700" htmlFor="email">
            Email
          </label>
          <input
            type="email"
            id="email"
            name={email}
            {{-- onChange={(e) => setEmail(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-gray-700" htmlFor="password">
            Password
          </label>
          <input
            type="password"
            id="password"
            name={password}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-gray-700" htmlFor="confirmPassword">
            Confirm Password
          </label>
          <input
            type="password"
            id="confirmPassword"
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-gray-700" htmlFor="phoneNumber">
            Phone
          </label>
          <input
            type="text"
            id="phoneNumber"
            name={phoneNumber}
            {{-- onChange={(e) => setPhoneNumber(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>          
        <div>
          <label class="block text-gray-700" htmlFor="dob">
            DOB
          </label>
          <input
            type="date"
            id="dob"
            name={dob}
            {{-- onChange={(e) => setDob(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>          
        <div>
          <label class="block text-gray-700" htmlFor="address">
            Address
          </label>
          <input
            type="text"
            id="address"
            name={address}
            {{-- onChange={(e) => setAddress(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>          
        <div>
          <label class="block text-gray-700" htmlFor="nida">
            NIDA
          </label>
          <input
            type="text"
            id="nida"
            name={nida}
            {{-- onChange={(e) => setNida(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>          
        <div>
          <label class="block text-gray-700" htmlFor="gender">
            Gender
          </label>
          <select
            name="gender"
            id="gender"
            name={gender}
            {{-- onChange={(e) => setGender(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
            >
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>         
         <div>
          <label class="block text-gray-700" htmlFor="occupation">
            Occupation
          </label>
          <input
            type="text"
            id="occupation"
            name={occupation}
            {{-- onChange={(e) => setOccupation(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>
        <div>
          <label class="block text-gray-700" htmlFor="maritalStatus">
            Marital Status
          </label>

          <select
            id="maritalStatus"
            name="maritalStatus"
            name={maritalStatus}
            {{-- onChange={(e) => setMaritalStatus(e.target.value)} --}}
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          >
            <option value="">Select</option>
            <option value="single">Single</option>
            <option value="married">Married</option>
            <option value="divorced">Divorced</option>
            <option value="widowed">Widowed</option>
          </select>
          
        </div>
        <button
          type="submit"
          class="w-full py-2 mt-4 text-white bg-gradient-to-r from-purple-500 to-green-500 rounded-full shadow-md hover:bg-gradient-to-l"
        >
          SIGN UP
        </button>
      </form>
      <div class="flex flex-col items-center mt-6">
        <p class="text-sm text-gray-700">OR</p>
        <button
          onClick={handleGoogleSubmit}
          type="button"
          class="mt-2 w-full py-2 text-gray-700 bg-white border border-gray-300 rounded-full shadow-md flex items-center justify-center gap-2 hover:bg-gray-100"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            class="w-5 h-5 text-blue-500"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="2"
              d="M12 14l9-5-9-5-9 5zm0 7l7-4-7-4-7 4 7 4z"
            />
          </svg>
          <span>Continue with Google</span>
        </button>
      </div>
      <div class="flex justify-center mt-4">
        <p class="text-sm text-gray-700">
          Already have an account?
          <a href="/login" class="text-purple-600 hover:underline">
            Login
          </a>
        </p>
      </div>
    </div>
  </div>


</body>