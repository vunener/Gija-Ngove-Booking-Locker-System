<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gija-Ngove Locker Booking System</title>
    
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-image: url('images/lockers.png');
        }

        .page-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

      
        .container {
            text-align: center;
             background-image: url('images/lockers.png');
        }

        .button-group {
            margin-top: 20px;
        }

        .btn, .btn-secondary {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            color: white;
            background-color: #233985ff;
            border-radius: 4px;
        }

        .btn-secondary {
            background-color: #233985ff;
        }

        .btn:hover, .btn-secondary:hover {
            opacity: 0.8;
        }

        .containerAD {
    background-color: #d1f0cbff;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 600px;
    width: 100%;
    text-align: center;
}
footer {
    flex-shrink: 0;
    background-color: #233985ff; 
    padding: 15px 10px;
    color: white;
    text-align: center;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    footer {
        padding: 10px 5px;
        font-size: 0.85rem;
    }
}

    </style>
</head>
<body>
    <div class="page-container">
        <?php include 'menu.inc'; ?>

        <main>
    <form action="login.php" method="get">
        <div class="containerAD">
            <header>
                <h1>Welcome to the Gija-Ngove Locker Booking System</h1>
            </header>
            <p>This system allows parents to book lockers for Students.</p>

            <div class="button-group">
                <button type="submit" class="btn">Login</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='registration.php'">Register as a Parent</button>
            </div>
        </div>
    </form>
</main>


        <footer>
         <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>


