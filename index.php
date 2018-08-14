<?php

require_once "vendor/autoload.php";

// Create app
$app = new Slim\App;

// Load configuration with dotenv
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// Get container
$container = $app->getContainer();

// Register Twig component on container to use view templates
$container['view'] = function() {
    return new Slim\Views\Twig('views');
};

// Load and initialize MessageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};

// Render landing page
$app->get('/', function($request, $response) {
    return $this->view->render($response, 'landing.html.twig', []);
});

// Handle callback request
$app->post('/callme', function($request, $response) {
    // Check if user has provided input for all form fields
    $name = $request->getParsedBodyParam('name', '');
    $number = $request->getParsedBodyParam('number', '');    
    if ($name == '' || $number == '') {
        // If not, show an error
        return $this->view->render($response, 'landing.html.twig', [
            'error' => "Please fill all required fields!",
            'name' => $name,
            'number' => $number
        ]);
    }

    // Choose one of the sales agent numbers randomly
    // a) Convert comma-separated values to array
    $numbers = explode(',', getenv('SALES_AGENT_NUMBERS'));
    // b) Random number between 0 and (number count - 1)
    $randomIndex = rand(0, count($numbers) - 1);
    // c) Pick number
    $recipient = $numbers[$randomIndex];

    // Prepare lead message
    $message = new MessageBird\Objects\Message;
    $message->originator = getenv('MESSAGEBIRD_ORIGINATOR');
    $message->recipients = [ $recipient ];
    $message->body = "You have a new lead: ".$name.". Call them at ".$number;

    // Send lead message with MessageBird API
    try {
        $this->messagebird->messages->create($message);
    } catch (Exception $e) {
        return $this->view->render($response, 'landing.html.twig', [
            'error' => "An error occurred while requesting a callback!",
            'name' => $name,
            'number' => $number
        ]);
    }

     // Message was sent successfully
     return $this->view->render($response, 'sent.html.twig', []);
});

// Start the application
$app->run();