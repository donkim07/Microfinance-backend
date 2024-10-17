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
          
        <div>
          <label class="block text-gray-700" htmlFor="email">
            Email
          </label>
          <input
            type="email"
            id="email"
            name="email"
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
            name=password
            class="w-full px-3 py-2 mt-1 text-gray-900 bg-white bg-opacity-25 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-600 focus:border-transparent"
          />
        </div>
        
        <button
          type="submit"
          class="w-full py-2 mt-4 text-white bg-gradient-to-r from-purple-500 to-green-500 rounded-full shadow-md hover:bg-gradient-to-l"
        >
          LOGIN
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
          Don't have an account?
          <a href="/signup" class="text-purple-600 hover:underline">
            Signup
          </a>
        </p>
      </div>
    </div>
  </div>


</body>