# Instant Lead Alerts
### ⏱ 15 min build time

## Why build instant lead alerts for Sales? 

Even though a lot of business transactions happen on the web, from both a business and user perspective, it's still often preferred to switch the channel and talk on the phone. Especially when it comes to high-value transactions in industries such as real estate or mobility, personal contact is essential.

One way to streamline this workflow is by building callback forms onto your website. Through these forms, customers can enter their contact details and receive a call to their phone, thus skipping queues where prospective leads need to stay on hold. 

Callback requests reflect a high level of purchase intent and should be dealt with as soon as possible to increase the chance of converting a lead. Therefore it's paramount to get them pushed to a sales agent as quickly as possible. SMS messaging has proven to be one of the most instant and effective channels for this use case.

In this MessageBird Developer Guide, we'll show how to implement a callback form on a PHP-based website with SMS integration powered by MessageBird for a fictitious car dealership named M.B. Cars.

## Getting Started

You need to have PHP installed on your machine to run the sample application. If you're using a Mac, PHP is already installed. For Windows, you can [get it from windows.php.net](https://windows.php.net/download/). Linux users, please check your system's default package manager. You also need Composer, which is available from [getcomposer.org](https://getcomposer.org/download/), to install application dependencies like the [MessageBird SDK for PHP](https://github.com/messagebird/php-rest-api).

The source code is available in the [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/lead-alerts-guide-php) from which it can be cloned or downloaded into your development environment.

After saving the code, open a console for the download directory and run the following command, which downloads the Slim framework, MessageBird SDK and other dependencies defined in the `composer.json` file:

````bash
composer install
````

It's helpful to know the basics of the [Slim framework](https://packagist.org/packages/slim/slim) to follow along with the tutorial, but you should be able to get the gist of it also if your experience lies with other frameworks.

## Configuring the MessageBird SDK

The SDK, which is used to send messages, is listed as a dependency in `composer.json`:

````json
{
    "require" : {
        "messagebird/php-rest-api" : "^1.9.4"
        ...
    }
}
````

An application can access the SDK, which is made available through Composer autoloading, by creating an instance of the `MessageBird\Client` class. The constructor takes a single argument, your API key. For frameworks like Slim you can add the SDK to the dependency injection container:

````php
// Load and initialize MessageBird SDK
$container['messagebird'] = function() {
    return new MessageBird\Client(getenv('MESSAGEBIRD_API_KEY'));
};
````
You need an API key, which you can retrieve from [the MessageBird dashboard](https://dashboard.messagebird.com/en/developers/access). As you can see in the code example above, the key is loaded from an environment variable called MESSAGEBIRD_API_KEY. With [dotenv](https://packagist.org/packages/vlucas/phpdotenv) you can define these variables in a `.env` file.

The repository contains an `env.example` file which you can copy to `.env` and then enter your information.

Apart from the API key, we also specify the originator, which is what is displayed as the sender of the messages. Please note that alphanumeric sender IDs like the one in our example file don't work in all countries, most importantly, they don't work in the United States. If you can't use alphanumeric IDs, use a real phone number instead.

Additionally, we specify the sales agent's telephone numbers. These are the recipients that will receive the SMS alerts when a potential customer submits the callback form. You can separate multiple numbers with commas.

Here's an example of a valid `.env` file for our sample application:

````env
MESSAGEBIRD_API_KEY=YOUR-API-KEY
MESSAGEBIRD_ORIGINATOR=MBCars
SALES_AGENT_NUMBERS=+31970XXXXXXX,+31970YYYYYYY
````

## Showing a Landing Page

The landing page is a simple HTML page with information about our company, a call to action and a form with two input fields, name and number, and a submit button. We use [Twig templates](https://twig.symfony.com/), so we can compose the view with a layout and have the ability to show dynamic content. You can see the landing page in the file `views/landing.html.twig`, which extends the layout stored in `views/layouts.html.twig`. The `$app->get('/')` route in `index.php` is responsible for rendering it.

## Handling Callback Requests

When the user submits the form, the `$app->post('/callme')` route receives their name and number. First, we fetch the input and do some validation:

````php
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
````

Then, we define where to send the message. As you've seen above, we specified multiple recipient numbers in the SALES_AGENT_NUMBERS environment variable. M.B. Cars have decided to randomly distribute incoming calls to their staff so that every salesperson receives roughly the same amount of leads. Here's the code for the random distribution:

````php
    // Choose one of the sales agent numbers randomly
    // a) Convert comma-separated values to array
    $numbers = explode(',', getenv('SALES_AGENT_NUMBERS'));
    // b) Random number between 0 and (number count - 1)
    $randomIndex = rand(0, count($numbers) - 1);
    // c) Pick number
    $recipient = $numbers[$randomIndex];
````

Now we can formulate a message for the agent as an instance of the `MessageBird\Objects\Message` class:

````php
    // Send lead message with MessageBird API
    // Prepare lead message
    $message = new MessageBird\Objects\Message;
    $message->originator = getenv('MESSAGEBIRD_ORIGINATOR');
    $message->recipients = [ $recipient ];
    $message->body = "You have a new lead: ".$name.". Call them at ".$number;
````

There are three object attributes:
- `originator`: The sender ID comes from the environment variable defined earlier.
- `recipients`: The API supports an array of recipients; we're sending to only one, but this attribute still has to be specified as an array.
- `body`: The text of the message that includes the input from the form.

Now, we can send our object through the MessageBird SDK using the `messages->create()` method:

The API request is surrounded with a try-catch construct to handle any errors that the SDK throws as exceptions. Inside the catch block, we handle the error case by showing the previous form again and inform the user that something went wrong. In the success case, we show a basic confirmation page which you can see in `views/sent.html.twig`.

````php
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
````

## Testing the Application

Have you created your `.env` file with a working key and added one more existing phone numbers, as explained above in _Configuring the MessageBird SDK_, to receive the lead alert? Awesome!

Now run the following command from your console:

````bash
php -S 0.0.0.0:8080 index.php
````

Go to http://localhost:8080/ to see the form and request a lead!

## Nice work!

You've just build your own instant lead alerts application with MessageBird!

You can now use the flow, code snippets and UI examples from this tutorial as an inspiration to build your own SMS-based lead alerts application. Don't forget to download the code from the [MessageBird Developer Guides GitHub repository](https://github.com/messagebirdguides/lead-alerts-guide-php).


## Next steps

Want to build something similar but not quite sure how to get started? Please feel free to let us know at support@messagebird.com, we'd love to help!# lead-alerts-guide-php
