<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite('resources/css/app.css')

    </head>
<body>


<div class="flex h-screen w-screen" ref={backgroundRef}>
    {/* Sidebar */}
    <Sidebar />

    {/* Main Content */}
    <div class="flex-1 ml-auto p-12">
      <div class='flex ml-auto items-center mb-6'>
        {/* <Link href="/" legacyBehavior>
          <a class="ml-auto ml-3">
            <Image 
              src="/icons/logo.svg"
              width={34}
              height={34}
              alt="Adrian logo"
            />
          </a>
        </Link> */}
        <h1 class="text-2xl font-bold text-gray-800 ml-auto ml-2"></h1>
      </div>
      <br />

      {/* Welcome and Credentials Section */}
      <div class="welcome-section bg-gray-100 rounded-lg shadow-2xl p-8">
        <h2 class="text-2xl font-semibold mb-4 text-gray-800">Welcome Laurent!</h2>
        <p class="mb-6 text-gray-700">
          Meet Adriano, your trusted partner for financial solutions. We are here to help you achieve your financial goals.
        </p>
        <div class="flex">
          {/* Left Side - 60% */}
          <div class="w-4/5 mb-8">
            <h3 class="text-lg font-medium text-gray-800">Your Credentials:</h3>
            <p class="text-gray-700">First Name: Laurent</p>
            <p class="text-gray-700">Surname: Adriano</p>
            <p class="text-gray-700">Phone Number: +255784461743</p>
            <p class="text-gray-700">Email: adriandevelopment@gmail.com</p>
            <p class="text-gray-700">Date of Birth: 14 Feb 1969</p>
            <p class="text-gray-700">Address: Business Street, Mwanza</p>
            <p class="text-gray-700">Gender: Male</p>
            <p class="text-gray-700">Occupation: Business Man</p>
            <p class="text-gray-700">Marital Status: Married</p>
          </div>

          {/* Right Side - 40% */}
          <div class="w-2/5 mb-8">
            <LoanStatusDisplay />
          </div>
        </div>

        <div class="flex space-x-8">
          <Button
            onClick={() => router.push('/credentials')}
            class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
          >
            Update Credentials
          </Button>
          <Button
            onClick={() => router.push('/loan-application')}
            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            Apply for a Loan
          </Button>
          <Button
            onClick={toggleLoanHistory} 
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            {showLoanHistory ? 'Hide Loan History' : 'Full Loan History'}
          </Button>
        </div>
        {/* Loan History Section (Conditional) */}
        {showLoanHistory && (
          <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-800">Your Loan History:</h3>
            {isLoading ? (
              <div class="flex justify-center items-center">
                <Loader2 size={30} class="animate-spin" />
              </div>
            ) : (
              <div class="overflow-x-auto mb-4">
                {loanHistory.length === 0 ? (
                  <p>No loan history available.</p>
                ) : (
                  <LoanTable loanHistory={loanHistory} /> // Render the LoanTable component
                )}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  </div>