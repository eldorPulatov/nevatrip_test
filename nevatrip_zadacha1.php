<?php

// Функция для генерации уникального штрих-кода
function generateBarcode($length = 8) {
    return substr(str_shuffle("0123456789"), 0, $length);
}

// Функция для добавления заказа
function addOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $user_id) {
    // Устанавливаем соединение с базой данных SQLite
    $dbPath = 'nevatrip_bd.db';
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Вычисляем общую цену заказа
    $equal_price = ($ticket_adult_price * $ticket_adult_quantity) + ($ticket_kid_price * $ticket_kid_quantity);
    
    // Генерация уникального barcode и бронирование
    do {
        $barcode = generateBarcode(); // Генерация случайного штрих-кода
        $bookingResponse = makeBookingRequest($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode);
        
        // Если штрих-код уже существует, повторяем генерацию
    } while (isset($bookingResponse['error']) && $bookingResponse['error'] == 'barcode already exists');
    
    // Если бронирование успешно, делаем запрос на подтверждение
    if (isset($bookingResponse['message']) && $bookingResponse['message'] == 'order successfully booked') {
        $approvalResponse = makeApprovalRequest($barcode);

        // Проверка успешности подтверждения
        if (isset($approvalResponse['message']) && $approvalResponse['message'] == 'order successfully approved') {
            // Подготовка и выполнение SQL-запроса для добавления заказа в базу
            $stmt = $db->prepare("INSERT INTO orders (event_id, event_date, ticket_adult_price, ticket_adult_quantity, ticket_kid_price, ticket_kid_quantity, barcode, user_id, equal_price, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))");
            $stmt->bindValue(1, $event_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $event_date, PDO::PARAM_STR);
            $stmt->bindValue(3, $ticket_adult_price, PDO::PARAM_INT);
            $stmt->bindValue(4, $ticket_adult_quantity, PDO::PARAM_INT);
            $stmt->bindValue(5, $ticket_kid_price, PDO::PARAM_INT);
            $stmt->bindValue(6, $ticket_kid_quantity, PDO::PARAM_INT);
            $stmt->bindValue(7, $barcode, PDO::PARAM_STR);
            $stmt->bindValue(8, $user_id, PDO::PARAM_INT);
            $stmt->bindValue(9, $equal_price, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo "Заказ успешно добавлен!";
            } else {
                echo "Ошибка при добавлении заказа: " . $stmt->errorInfo()[2];
            }
        } else {
            echo "Ошибка подтверждения заказа: " . $approvalResponse['error'];
        }
    } else {
        echo "Ошибка бронирования заказа: " . $bookingResponse['error'];
    }

    // Закрываем соединение
    $db = null;
}

// Функция для отправки запроса на бронь
function makeBookingRequest($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode) {
    $url = 'https://api.site.com/book';
    $data = [
        'event_id' => $event_id,
        'event_date' => $event_date,
        'ticket_adult_price' => $ticket_adult_price,
        'ticket_adult_quantity' => $ticket_adult_quantity,
        'ticket_kid_price' => $ticket_kid_price,
        'ticket_kid_quantity' => $ticket_kid_quantity,
        'barcode' => $barcode
    ];

    return sendPostRequest($url, $data);
}

// Функция для отправки запроса на подтверждение
function makeApprovalRequest($barcode) {
    $url = 'https://api.site.com/approve';
    $data = ['barcode' => $barcode];

    return sendPostRequest($url, $data);
}

// Вспомогательная функция для отправки POST-запросов
function sendPostRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Установка таймаута

    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        // Если произошла ошибка cURL
        return ['error' => curl_error($ch)];
    }

    curl_close($ch);
    return json_decode($result, true);
}

// Пример вызова функции добавления заказа
addOrder(3, '2021-08-21 13:00:00', 700, 1, 450, 0, 451);
?>
