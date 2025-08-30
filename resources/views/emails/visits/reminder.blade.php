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
            background: white;
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
            margin-top: 28px;
            margin-bottom: 40px;
            gap: 12px;
            display: flex;
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

    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://fizjoterapia-kaczmarek.pl/wp-content/uploads/2025/08/logo-basic.png" class="logo" />
        </div>
        <div class="content">
            <h2>Przypomnienie o wizycie</h2>
            <p>Cześć {{ $visit->user->name ?? 'Kliencie' }},</p>
            <p class="text-own">przypominamy o Twojej wizycie w naszym gabinecie. Zobacz jej szczegóły:</p>
            <div class="flex flex-col gap-[2px]">
                <p class="text-list">
                    Zabieg: <span class="text-list-data"> {{ $visit->type }}</span>
                </p>

                <p class="text-list">
                    Termin: <span class="text-list-data"> {{ \Carbon\Carbon::parse($visit->date)->format('d.m.Y') }}</span>

                </p>
                <p class="text-list">
                    Godzina: <span class="text-list-data">{{ \Carbon\Carbon::parse($visit->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($visit->end_time)->format('H:i') }}</span>

                </p>
                <p class="text-list">
                    Lekarz: <span class="text-list-data">{{ $visit->doctor->name }} {{ $visit->doctor->surname }}</span>


                </p>
            </div>


            @php
            $frontendUrl = env('FRONTEND_URL', 'https://panel.fizjoterapia-kaczmarek.pl');
            @endphp

            <div class="button-container">
                <!-- Zmień termin -->
                <a href="{{ $frontendUrl.'/zmien-termin-'.$visit->user->id.'-'.$visit->id }}" class="button">
                    Zmień termin
                </a>

                <!-- Odwołaj wizytę -->
                <a href="{{ $frontendUrl.'/odwolaj-wizyte-'.$visit->user->id.'-'.$visit->id }}" class="remove-button">
                    Odwołaj wizytę
                </a>
            </div>


            <div class="info-section">
                <h3 class="info-title">Jak przygotować się do wizyty?</h3>
                <ul class="info-list">
                    <li>Przyjdź kilka minut wcześniej, aby spokojnie się przygotować.</li>
                    <li>Zabierz wygodny strój sportowy (krótkie spodenki, koszulka, obuwie sportowe).</li>
                    <li>Przynieś dokumentację medyczną (wyniki badań, opis RTG, rezonansu).</li>
                    <li>Unikaj ciężkich posiłków bezpośrednio przed wizytą.</li>
                </ul>
                <h3 class="info-title" style="margin-top: 38px;">Jak do nas trafić?</h3>
                <p class="info-text">
                    📍 Gabinet znajduje się przy <strong>Zespół Krytych Pływalni AKF</strong> w Krakowie na al. Jana Pawła II 78, pietro -1<br>
                </p>
                <p class="info-text">
                    Parking dostępny dla pacjentów przed budynkiem<br>
                    Dojazd komunikacją miejską – przystanek <strong>AKF/PK</strong><br>
                </p>
                <p class="info-text">
                    👉 Kliknij tutaj, aby otworzyć
                    <a href="https://maps.app.goo.gl/mjJBatPQuwyYr1AP6" class="info-link">mapę Google</a>
                </p>
            </div>
            <p class="before-footer">Do zobaczenia!<br>Zespół Fizjoterapia Kaczmarek</p>
        </div>
        <div class="footer">
            © {{ date('Y') }} Fizjoterapia Kaczmarek
        </div>
    </div>
</body>
</html>
