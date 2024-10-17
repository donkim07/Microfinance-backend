<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite('resources/css/app.css')

    </head>

    <div class="relative flex flex-col items-center justify-center min-h-screen bg-gradient-to-r from-green-300 to-purple-500 text-white overflow-hidden">
  
        <div class="text-center z-10">
            <h1 class="text-5xl font-extrabold mb-4 bg-transparent">
                Welcome to Adrian CIMS
            </h1>
        </div>
        <p class="text-xl mb-4 break-words bg-transparent paragraph-1">
            Simplify your customer management and loan processes with Adrian CIMS.
        </p>
        <p class="text-xl mb-4 break-words bg-transparent paragraph-2">
            Experience seamless data organization, efficient loan tracking, and enhanced customer engagement.
        </p>
        
        <p >Lorem ipsum dolor sit, amet consectetur adipisicing elit. Veritatis deserunt, nisi blanditiis rem voluptatem et
          <br /> perferendis explicabo, harum at sint dolor? Aperiam deserunt blanditiis, dignissimos quos esse odio ipsam eaque.</p>
  
        <div class="flex gap-8 mt-8 z-10">
          <a
            href="/login"
            class="bg-green-600 text-white px-8 py-3 rounded-full hover:bg-green-800 transition duration-300 ease-in-out"
          >
            Login
          </a>
          <a
            href="/sign-up"
            class="bg-purple-600 text-white px-8 py-3 rounded-full hover:bg-purple-800 transition duration-300 ease-in-out"
          >
            Sign Up
          </a>
        </div>
  
        <footer class="absolute bottom-4 text-center w-full z-10">
          <p class="text-sm text-gray-200">
            © 2024 Adrian CIMS. All rights reserved.
          </p>
        </footer>
      </div>



      <script>
        document.addEventListener('DOMContentLoaded', () => {
            const circlesContainer = document.createElement('div');
            circlesContainer.className = 'absolute top-0 left-0 w-full h-full overflow-hidden';
            document.body.appendChild(circlesContainer);
    
            const circles = [];
    
            const createCircles = () => {
                for (let i = 0; i < 15; i++) {
                    const size = Math.random() * 60 + 20;
                    const circle = document.createElement('div');
                    circle.className = 'absolute rounded-full';
                    circle.style.width = `${size}px`;
                    circle.style.height = `${size}px`;
                    circle.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
                    circle.style.left = `${Math.random() * window.innerWidth}px`;
                    circle.style.top = `${Math.random() * window.innerHeight}px`;
                    circle.style.transform = 'translate(-50%, -50%)';
                    circle.style.filter = 'blur(6px)';
    
                    circles.push({ element: circle, x: parseFloat(circle.style.left), y: parseFloat(circle.style.top), size });
                    circlesContainer.appendChild(circle);
                }
            };
    
            const animateCircles = () => {
                circles.forEach((circle) => {
                    circle.y = (circle.y + 0.5) % window.innerHeight;
                    circle.x = (circle.x + Math.sin(circle.y / 100)) % window.innerWidth;
                    circle.element.style.top = `${circle.y}px`;
                    circle.element.style.left = `${circle.x}px`;
                });
    
                requestAnimationFrame(animateCircles);
            };
    
            createCircles();
            animateCircles();
        });
    </script>


<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            document.querySelector('.paragraph-1').classList.add('fade-in-up');
        }, 330); // Delay for the first paragraph

        setTimeout(() => {
            document.querySelector('.paragraph-2').classList.add('fade-in-up');
        }, 400); // Delay for the second paragraph
    });
</script>



    