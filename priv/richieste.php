<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// File di configurazione
define('REQUESTS_FILE', 'requests-data.json');
define('CALENDAR_FILE', 'calendario.json');

// Funzione per leggere un file JSON
function readJsonFile($filename) {
    if (!file_exists($filename)) {
        return null;
    }
    
    $content = file_get_contents($filename);
    if ($content === false) {
        return null;
    }
    
    $data = json_decode($content, true);
    return $data;
}

// Funzione per scrivere un file JSON
function writeJsonFile($filename, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    
    return file_put_contents($filename, $json) !== false;
}

// Funzione per creare backup
function createBackup($filename) {
    if (file_exists($filename)) {
        $backupName = $filename . '.backup.' . date('Y-m-d_H-i-s');
        copy($filename, $backupName);
    }
}

// Funzione per ottenere tutte le richieste
function getRequests() {
    $data = readJsonFile(REQUESTS_FILE);
    
    if ($data === null) {
        // Se il file non esiste, crea una struttura base
        $data = [
            'lastUpdated' => date('c'),
            'totalRequests' => 0,
            'requests' => []
        ];
        writeJsonFile(REQUESTS_FILE, $data);
    }
    
    return [
        'success' => true,
        'data' => $data['requests'] ?? [],
        'total' => $data['totalRequests'] ?? 0,
        'lastUpdated' => $data['lastUpdated'] ?? null
    ];
}

// Funzione per aggiungere una nuova richiesta
function addRequest($requestData) {
    $data = readJsonFile(REQUESTS_FILE);
    
    if ($data === null) {
        $data = [
            'lastUpdated' => date('c'),
            'totalRequests' => 0,
            'requests' => []
        ];
    }
    
    // Genera nuovo ID
    $maxId = 0;
    if (!empty($data['requests'])) {
        foreach ($data['requests'] as $request) {
            if ($request['id'] > $maxId) {
                $maxId = $request['id'];
            }
        }
    }
    $newId = $maxId + 1;
    
    // Crea la nuova richiesta
    $newRequest = [
        'id' => $newId,
        'name' => $requestData['name'] ?? '',
        'email' => $requestData['email'] ?? '',
        'phone' => $requestData['phone'] ?? '',
        'car' => $requestData['car'] ?? '',
        'message' => $requestData['message'] ?? '',
        'status' => 'pending',
        'date' => date('Y-m-d'),
        'created_at' => date('c'),
        'approved_date' => null
    ];
    
    // Aggiungi all'array
    array_unshift($data['requests'], $newRequest);
    $data['totalRequests'] = count($data['requests']);
    $data['lastUpdated'] = date('c');
    
    // Crea backup prima di salvare
    createBackup(REQUESTS_FILE);
    
    // Salva
    if (writeJsonFile(REQUESTS_FILE, $data)) {
        return [
            'success' => true,
            'message' => 'Richiesta aggiunta con successo',
            'data' => $newRequest
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Errore durante il salvataggio della richiesta'
        ];
    }
}

// Funzione per aggiornare lo status di una richiesta
function updateRequestStatus($requestId, $status) {
    $data = readJsonFile(REQUESTS_FILE);
    
    if ($data === null) {
        return [
            'success' => false,
            'message' => 'File richieste non trovato'
        ];
    }
    
    // Trova la richiesta
    $requestIndex = -1;
    for ($i = 0; $i < count($data['requests']); $i++) {
        if ($data['requests'][$i]['id'] == $requestId) {
            $requestIndex = $i;
            break;
        }
    }
    
    if ($requestIndex === -1) {
        return [
            'success' => false,
            'message' => 'Richiesta non trovata'
        ];
    }
    
    // Aggiorna lo status
    $data['requests'][$requestIndex]['status'] = $status;
    $data['requests'][$requestIndex]['updated_at'] = date('c');
    
    if ($status === 'approved') {
        $data['requests'][$requestIndex]['approved_date'] = date('Y-m-d');
    }
    
    $data['lastUpdated'] = date('c');
    
    // Crea backup
    createBackup(REQUESTS_FILE);
    
    // Salva
    if (writeJsonFile(REQUESTS_FILE, $data)) {
        return [
            'success' => true,
            'message' => 'Status aggiornato con successo',
            'data' => $data['requests'][$requestIndex]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Errore durante il salvataggio'
        ];
    }
}

// Funzione per eliminare una richiesta
function deleteRequest($requestId) {
    $data = readJsonFile(REQUESTS_FILE);
    
    if ($data === null) {
        return [
            'success' => false,
            'message' => 'File richieste non trovato'
        ];
    }
    
    // Trova e rimuovi la richiesta
    $originalCount = count($data['requests']);
    $data['requests'] = array_filter($data['requests'], function($request) use ($requestId) {
        return $request['id'] != $requestId;
    });
    
    // Reindexizza l'array
    $data['requests'] = array_values($data['requests']);
    
    if (count($data['requests']) === $originalCount) {
        return [
            'success' => false,
            'message' => 'Richiesta non trovata'
        ];
    }
    
    $data['totalRequests'] = count($data['requests']);
    $data['lastUpdated'] = date('c');
    
    // Crea backup
    createBackup(REQUESTS_FILE);
    
    // Salva
    if (writeJsonFile(REQUESTS_FILE, $data)) {
        return [
            'success' => true,
            'message' => 'Richiesta eliminata con successo'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Errore durante l\'eliminazione'
        ];
    }
}

// Funzione per approvare una richiesta e aggiungerla al calendario
function approveRequest($requestId, $startDate, $endDate, $price, $notes = '') {
    // Prima aggiorna lo status della richiesta
    $updateResult = updateRequestStatus($requestId, 'approved');
    if (!$updateResult['success']) {
        return $updateResult;
    }
    
    // Ottieni i dati della richiesta
    $requestsData = readJsonFile(REQUESTS_FILE);
    $request = null;
    foreach ($requestsData['requests'] as $r) {
        if ($r['id'] == $requestId) {
            $request = $r;
            break;
        }
    }
    
    if (!$request) {
        return [
            'success' => false,
            'message' => 'Richiesta non trovata dopo l\'aggiornamento'
        ];
    }
    
    // Carica o crea i dati del calendario
    $calendarData = readJsonFile(CALENDAR_FILE);
    
    if ($calendarData === null) {
        $calendarData = [
            'lastUpdated' => date('c'),
            'totalBookings' => 0,
            'bookings' => []
        ];
    }
    
    // Genera nuovo ID per la prenotazione
    $maxBookingId = 0;
    if (!empty($calendarData['bookings'])) {
        foreach ($calendarData['bookings'] as $booking) {
            if ($booking['id'] > $maxBookingId) {
                $maxBookingId = $booking['id'];
            }
        }
    }
    $newBookingId = $maxBookingId + 1;
    
    // Crea la prenotazione
    $newBooking = [
        'id' => $newBookingId,
        'requestId' => $requestId,
        'clientName' => $request['name'],
        'clientEmail' => $request['email'],
        'clientPhone' => $request['phone'] ?? '',
        'car' => $request['car'],
        'startDate' => $startDate,
        'endDate' => $endDate,
        'price' => $price,
        'notes' => $notes,
        'status' => 'confirmed',
        'approvedDate' => date('Y-m-d'),
        'createdAt' => date('c'),
        'originalMessage' => $request['message']
    ];
    
    // Aggiungi la prenotazione al calendario
    array_unshift($calendarData['bookings'], $newBooking);
    $calendarData['totalBookings'] = count($calendarData['bookings']);
    $calendarData['lastUpdated'] = date('c');
    
    // Crea backup del calendario
    createBackup(CALENDAR_FILE);
    
    // Salva il calendario
    if (writeJsonFile(CALENDAR_FILE, $calendarData)) {
        return [
            'success' => true,
            'message' => 'Richiesta approvata e aggiunta al calendario con successo',
            'data' => [
                'request' => $request,
                'booking' => $newBooking
            ]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Richiesta approvata ma errore nel salvataggio del calendario'
        ];
    }
}

// Funzione per validare i dati di input
function validateRequestData($data) {
    $errors = [];
    
    if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
        $errors[] = 'Nome deve essere di almeno 2 caratteri';
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida';
    }
    
    if (empty($data['car']) || strlen(trim($data['car'])) < 2) {
        $errors[] = 'Auto deve essere specificata';
    }
    
    if (empty($data['message']) || strlen(trim($data['message'])) < 10) {
        $errors[] = 'Messaggio deve essere di almeno 10 caratteri';
    }
    
    return $errors;
}

// Funzione per validare i dati di approvazione
function validateApprovalData($data) {
    $errors = [];
    
    if (empty($data['start_date']) || !strtotime($data['start_date'])) {
        $errors[] = 'Data inizio non valida';
    }
    
    if (empty($data['end_date']) || !strtotime($data['end_date'])) {
        $errors[] = 'Data fine non valida';
    }
    
    if (!empty($data['start_date']) && !empty($data['end_date'])) {
        if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
            $errors[] = 'Data fine deve essere successiva alla data inizio';
        }
    }
    
    if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
        $errors[] = 'Prezzo deve essere un numero positivo';
    }
    
    return $errors;
}

// Gestione delle richieste HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // GET - Ottieni le richieste
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_requests') {
            echo json_encode(getRequests());
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Azione non valida'
            ]);
        }
        
    } elseif ($method === 'POST') {
        // POST - Gestisci le operazioni
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Dati JSON non validi');
        }
        
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'add_request':
                // Valida i dati
                $errors = validateRequestData($data);
                if (!empty($errors)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Errori di validazione: ' . implode(', ', $errors)
                    ]);
                    break;
                }
                
                $result = addRequest($data);
                echo json_encode($result);
                break;
                
            case 'update_status':
                if (empty($data['request_id']) || empty($data['status'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID richiesta e status sono obbligatori'
                    ]);
                    break;
                }
                
                $validStatuses = ['pending', 'approved', 'rejected'];
                if (!in_array($data['status'], $validStatuses)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Status non valido'
                    ]);
                    break;
                }
                
                $result = updateRequestStatus($data['request_id'], $data['status']);
                echo json_encode($result);
                break;
                
            case 'approve_request':
                if (empty($data['request_id'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID richiesta obbligatorio'
                    ]);
                    break;
                }
                
                // Valida i dati di approvazione
                $errors = validateApprovalData($data);
                if (!empty($errors)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Errori di validazione: ' . implode(', ', $errors)
                    ]);
                    break;
                }
                
                $result = approveRequest(
                    $data['request_id'],
                    $data['start_date'],
                    $data['end_date'],
                    $data['price'],
                    $data['notes'] ?? ''
                );
                echo json_encode($result);
                break;
                
            case 'delete_request':
                if (empty($data['request_id'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID richiesta obbligatorio'
                    ]);
                    break;
                }
                
                $result = deleteRequest($data['request_id']);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'message' => 'Azione non riconosciuta'
                ]);
                break;
        }
        
    } else {
        // Metodo non supportato
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Metodo non supportato'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Errore del server: ' . $e->getMessage()
    ]);
}

// Funzioni di utilità aggiuntive

// Funzione per ottenere statistiche
function getStatistics() {
    $requestsData = readJsonFile(REQUESTS_FILE);
    $calendarData = readJsonFile(CALENDAR_FILE);
    
    $stats = [
        'requests' => [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ],
        'bookings' => [
            'total' => 0,
            'thisMonth' => 0,
            'totalRevenue' => 0
        ]
    ];
    
    if ($requestsData && !empty($requestsData['requests'])) {
        $stats['requests']['total'] = count($requestsData['requests']);
        foreach ($requestsData['requests'] as $request) {
            $stats['requests'][$request['status']]++;
        }
    }
    
    if ($calendarData && !empty($calendarData['bookings'])) {
        $stats['bookings']['total'] = count($calendarData['bookings']);
        $currentMonth = date('Y-m');
        
        foreach ($calendarData['bookings'] as $booking) {
            // Revenue totale
            if (isset($booking['price'])) {
                $stats['bookings']['totalRevenue'] += $booking['price'];
            }
            
            // Prenotazioni questo mese
            if (isset($booking['startDate']) && strpos($booking['startDate'], $currentMonth) === 0) {
                $stats['bookings']['thisMonth']++;
            }
        }
    }
    
    return $stats;
}


?>