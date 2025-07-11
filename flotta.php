<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestione preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configurazione
$jsonFile = 'flotta.json';
$uploadDir = 'uploads/';

// Crea directory uploads se non esiste
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Funzioni helper
function readJsonFile($file) {
    if (!file_exists($file)) {
        return ['cars' => []];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: ['cars' => []];
}

function writeJsonFile($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

function isValidImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    return in_array($file['type'], $allowedTypes) && 
           $file['size'] <= $maxSize && 
           $file['error'] === UPLOAD_ERR_OK;
}

// Routing
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

try {
    switch ($method) {
        case 'GET':
            if ($path === '/cars' || $path === '/') {
                // Leggi tutti i veicoli
                $data = readJsonFile($jsonFile);
                echo json_encode($data);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint non trovato']);
            }
            break;
            
        case 'POST':
            if ($path === '/cars') {
                // Aggiungi nuovo veicolo
                $data = readJsonFile($jsonFile);
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dati non validi']);
                    break;
                }
                
                // Genera nuovo ID
                $newId = 1;
                if (!empty($data['cars'])) {
                    $newId = max(array_column($data['cars'], 'id')) + 1;
                }
                
                $newCar = [
                    'id' => $newId,
                    'brand' => $input['brand'] ?? '',
                    'title' => $input['title'] ?? '',
                    'price' => $input['price'] ?? '',
                    'image' => $input['image'] ?? '',
                    'engine' => $input['engine'] ?? '',
                    'power' => $input['power'] ?? '',
                    'fuel' => $input['fuel'] ?? '',
                    'seats' => $input['seats'] ?? '',
                    'transmission' => $input['transmission'] ?? '',
                    'year' => $input['year'] ?? '',
                    'description' => $input['description'] ?? '',
                    'features' => $input['features'] ?? []
                ];
                
                $data['cars'][] = $newCar;
                
                if (writeJsonFile($jsonFile, $data)) {
                    echo json_encode(['success' => true, 'car' => $newCar]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Errore nel salvare i dati']);
                }
            } elseif ($path === '/upload') {
                // Upload immagine
                if (!isset($_FILES['image'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Nessun file caricato']);
                    break;
                }
                
                $file = $_FILES['image'];
                
                if (!isValidImage($file)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File non valido. Sono accettati solo JPG, PNG, GIF, WEBP fino a 5MB']);
                    break;
                }
                
                $fileName = generateFileName($file['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    echo json_encode([
                        'success' => true,
                        'filename' => $fileName,
                        'url' => $filePath
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Errore nel caricamento del file']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint non trovato']);
            }
            break;
            
        case 'PUT':
            if (preg_match('/^\/cars\/(\d+)$/', $path, $matches)) {
                // Modifica veicolo esistente
                $carId = (int)$matches[1];
                $data = readJsonFile($jsonFile);
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dati non validi']);
                    break;
                }
                
                // Trova il veicolo
                $carIndex = -1;
                foreach ($data['cars'] as $index => $car) {
                    if ($car['id'] == $carId) {
                        $carIndex = $index;
                        break;
                    }
                }
                
                if ($carIndex === -1) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Veicolo non trovato']);
                    break;
                }
                
                // Aggiorna i dati
                $data['cars'][$carIndex] = [
                    'id' => $carId,
                    'brand' => $input['brand'] ?? $data['cars'][$carIndex]['brand'],
                    'title' => $input['title'] ?? $data['cars'][$carIndex]['title'],
                    'price' => $input['price'] ?? $data['cars'][$carIndex]['price'],
                    'image' => $input['image'] ?? $data['cars'][$carIndex]['image'],
                    'engine' => $input['engine'] ?? $data['cars'][$carIndex]['engine'],
                    'power' => $input['power'] ?? $data['cars'][$carIndex]['power'],
                    'fuel' => $input['fuel'] ?? $data['cars'][$carIndex]['fuel'],
                    'seats' => $input['seats'] ?? $data['cars'][$carIndex]['seats'],
                    'transmission' => $input['transmission'] ?? $data['cars'][$carIndex]['transmission'],
                    'year' => $input['year'] ?? $data['cars'][$carIndex]['year'],
                    'description' => $input['description'] ?? $data['cars'][$carIndex]['description'],
                    'features' => $input['features'] ?? $data['cars'][$carIndex]['features']
                ];
                
                if (writeJsonFile($jsonFile, $data)) {
                    echo json_encode(['success' => true, 'car' => $data['cars'][$carIndex]]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Errore nel salvare i dati']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint non trovato']);
            }
            break;
            
        case 'DELETE':
            if (preg_match('/^\/cars\/(\d+)$/', $path, $matches)) {
                // Elimina veicolo
                $carId = (int)$matches[1];
                $data = readJsonFile($jsonFile);
                
                // Trova e rimuovi il veicolo
                $originalCount = count($data['cars']);
                $data['cars'] = array_filter($data['cars'], function($car) use ($carId) {
                    return $car['id'] != $carId;
                });
                
                // Riordina gli indici
                $data['cars'] = array_values($data['cars']);
                
                if (count($data['cars']) < $originalCount) {
                    if (writeJsonFile($jsonFile, $data)) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Errore nel salvare i dati']);
                    }
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Veicolo non trovato']);
                }
            } elseif (preg_match('/^\/uploads\/(.+)$/', $path, $matches)) {
                // Elimina immagine
                $filename = $matches[1];
                $filePath = $uploadDir . $filename;
                
                if (file_exists($filePath)) {
                    if (unlink($filePath)) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Errore nell\'eliminazione del file']);
                    }
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'File non trovato']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint non trovato']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non supportato']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno del server: ' . $e->getMessage()]);
}
?>