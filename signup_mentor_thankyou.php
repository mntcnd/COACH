<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Thank You for Applying</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="icon" href="uploads/img/coachicon.svg" type="image/svg+xml">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        #bg-video {
            position: fixed;
            top: 0;
            left: 0;
            min-width: 100%;
            min-height: 100%;
            z-index: -1;
            object-fit: cover;
        }

        body {
            background: linear-gradient(135deg, #693B69, #693B69);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            padding: 20px;
            color: white;
        }

        .thankyou-box {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(40px);
            padding: 40px 30px;
            border-radius: 16px;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
        }

        .thankyou-box h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #fff;
        }

        .thankyou-box p {
            font-size: 1.1rem;
            color: #e0e0ff;
            margin-bottom: 25px;
        }

        .thankyou-box a {
            position: relative;
            overflow: hidden;
            width: 50%;
            display: inline-block;
            padding: 12px 15px;
            border: none;
            border-radius: 20px;
            background: linear-gradient(90deg, #921ce0 0%, #360051 100%);
            color: white;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            cursor: pointer;
            transition: 0.3s ease;
            text-decoration: none;
        }

        .thankyou-box a::before {
            content: "";
            position: absolute;
            top: 0;
            left: -75%;
            width: 50%;
            height: 100%;
            background: rgba(255, 255, 255, 0.5);
            transform: skewX(-25deg);
        }

        .thankyou-box a:hover::before {
            animation: shine 0.75s forwards;
        }

        @keyframes shine {
            0% {
                left: -75%;
            }
            100% {
                left: 125%;
            }
        }
    </style>
</head>
<body>
    <video autoplay muted loop id="bg-video">
        <source src="img/bgcode1.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>
    <div class="thankyou-box">
        <h1>Thank You for Applying!</h1>
        <p>Your application has been successfully submitted. Please wait for a confirmation email with further instructions.</p>
        <a href="index.php">Return to Home</a>
    </div>
</body>
</html>
