<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Login kurir</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

</body> 

<body class="bg-white">
    <div class="mx-auto my-0 min-h-full  max-w-[495px]">

        <div class="min-h-screen flex items-center justify-center px-4">
            <div class="w-full max-w-sm text-center">
    
                <!-- Ilustrasi -->
                <div class="mb-6">
                    <img
                        src="https://api.bintangsempurna.co.id/images/uploads/1768970437-HhuXft064X.png"
                        alt="Kurir"
                        class="mx-auto w-60"
                    />
                </div>
    
                <!-- Text -->
                <h1 class="text-lg font-semibold text-gray-900">
                    Ingin lebih mudah pengiriman
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Atur sekarang juga
                </p>
    
                <div>
                    <!-- Button -->
                    <div class="mt-6">
                        <a
                            href="https://fe-kurir.vercel.app/"
                            class="block w-full rounded-full bg-red-600 py-3 text-white font-semibold
                                   hover:bg-red-700 active:scale-95 transition"
                        >
                            Masuk
                        </a>
                    </div>
                </div>
    
            </div>
        </div>
    </div>
</body>


</html>
