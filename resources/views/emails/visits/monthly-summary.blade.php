<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <style>
    body {
        font-family: Arial, sans-serif;
        background: transparent;
        margin: 0;
        padding: 0;
    }

    .container {
        max-width: 550px;
        background: #ffffff !important;
        margin: 30px auto;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .remove-button {
        display: inline-block;
        font-weight: 600;
        color: #f43737;
        text-decoration: none;
        border: 1px solid #f43737;
        background: transparent;
        text-align: center;
        white-space: nowrap;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
        padding: 9px 20px;
        font-size: 16px;
        border-radius: 99px;
        transition: all 0.3s;
        font-family: 'Public Sans', sans-serif;
    }

    .remove-button:hover {
        background-color: rgba(244, 55, 55, 0.08);
        cursor: pointer;
    }


    .header {
        background: #d3f4fed8;
        color: white;
        padding: 21px;
        text-align: start;
    }

    .content {
        padding: 30px;
        color: #333333;
        line-height: 1.5;
    }

    .button {
        display: inline-block;
        font-weight: 600;
        color: white;
        background: #31a9ce;
        text-align: center;
        white-space: nowrap;
        text-decoration: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
        padding: 10px 20px;
        font-size: 16px;
        border: 1px solid transparent;
        border-radius: 99px;
        transition: all 0.3s;
    }

    .button:hover {
        background-color: #2c9fc2;
        cursor: pointer;
    }

    .footer {
        font-size: 12px;
        color: #888888;
        text-align: center;
        padding: 24px;
        background: #d3f4fed8;
        margin-top: 42px;
    }

    .logo {
        width: 130px !important;
        margin-bottom: 10px;
    }

    p {
        font-size: 17px;
        margin: 0;
    }

    h2 {
        font-size: 20px;
        margin-bottom: 14px;
        margin-top: 8px;
    }

    .text-list {
        font-size: 17px;
        margin-top: 2px;
    }

    .text-list-data {
        font-size: 17px;
        font-weight: 600;
        color: #31a9ce;
        margin: 0;
    }

    .text-own {
        margin-top: 2px;
        margin-bottom: 15px;
    }

    .before-footer {
        margin-top: 45px;
        font-size: 17px;
    }

    .button-container {
        margin-top: 35px;
        margin-bottom: 40px;
        gap: 12px;
        display: flex;
    }

    @media (max-width: 768px) {
        .button-container {
            flex-direction: column;
        }
    }


    /* 🔹 Nowe style dla sekcji informacyjnej */
    .info-section {
        margin-top: 32px;
        padding-top: 20px;
        border-top: 1px solid #eaeaea;
    }

    .info-title {
        font-size: 18px;
        margin-bottom: 15px;
    }

    .info-list {
        list-style: none;
        padding-left: 0;
        margin: 0;
    }

    .info-list li {
        font-size: 16px;
        line-height: 1.4;
        color: #333;
        margin-bottom: 12px;
        position: relative;
        padding-left: 15px;
    }

    .info-list li::before {
        content: "•";
        color: #31a9ce;
        font-weight: bold;
        position: absolute;
        left: 0;
        top: 0;
    }

    .info-text {
        font-size: 16px;
        line-height: 1.4;
        color: #333;
        margin-bottom: 13px;
    }

    .info-link {
        color: #31a9ce;
        text-decoration: underline;
    }

    .info-link-link {
        color: #31a9ce;
        font-size: 15px;
        text-decoration: underline;
    }

    .info-link-link-line {
        color: #d8d8d8;
        font-size: 18px;
        margin: 0 6px;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="https://fizjoterapia-kaczmarek.pl/wp-content/uploads/2025/08/logo-basic.png" class="logo" />
        </div>
        <div class="content">
        <h2>Podsumowanie wizyt – {{ $month }}</h2>

        @foreach($report as $doctor)
        <div class="doctor">
            <h3>{{ $doctor['doctor'] }}</h3>
            <p><strong>Liczba wizyt:</strong> {{ $doctor['total'] }}</p>

            <ul>
                @foreach($doctor['types'] as $type => $count)
                <li>{{ $type }} – {{ $count }}</li>
                @endforeach
            </ul>
        </div>
        @endforeach
        </div>
</body>

</html>
