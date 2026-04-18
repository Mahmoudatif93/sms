

![Build Status](/public/images/whatsapp-red-icon.svg) 

# DREAMS


## Whatsapp Solution Partner Laravel Backend App

---

## Overview

This repository contains a Laravel backend application designed to interact with the WhatsApp Business API. The application allows you to send messages, verify phone numbers, and handle various messaging tasks using the WhatsApp Business API.

---

## Features

- Send messages via WhatsApp
- Verify phone numbers for active WhatsApp accounts
- Modular and extensible codebase
- Secure authentication with Bearer tokens
- Comprehensive API documentation with Swagger

---

## Requirements

- PHP 8.0 or higher
- Composer
- Laravel 8 or higher
- WhatsApp Business API account
- MySQL or any other supported database

---

## Installation

1. Clone the repository:

    ```bash
    git clone git@bitbucket.org:dreamsbrand/whatsappapi.git
    cd whatsappapi
    ```

2. Install dependencies:

    ```bash
    composer install
    ```

3. Copy the example environment file and configure the environment variables:

    ```bash
    cp .env.example .env
    ```

   Update the `.env` file with your environment settings, particularly:

    ```
    FACEBOOK_GRAPH_API_BASE_URL=https://graph.facebook.com/
    FACEBOOK_GRAPH_API_VERSION=v19.0
    FACEBOOK_ACCESS_TOKEN=your_facebook_access_token
    FACEBOOK_PHONE_NUMBER_ID=your_phone_number_id
    ```

4. Generate an application key:

    ```bash
    php artisan key:generate
    ```

5. Run database migrations:

    ```bash
    php artisan migrate
    ```

6. Serve the application:

    ```bash
    php artisan serve
    ```

---

## Example Usage

### Sending a Message

To send a message via WhatsApp, use the following endpoint:

- **Endpoint**: `POST /send-message`
- **Body**:
    ```json
    {
        "toWhatsappPhoneNumber": "201126220806"
    }
    ```
- **Response**:
    - `200 OK` if the message is sent successfully
    - `400 Bad Request` if the input is invalid
    - `500 Internal Server Error` if there is a server error

---

### API Documentation

This application uses Swagger to document the API. You can access the Swagger UI to explore and test the API endpoints.

To generate Swagger documentation, run:

```bash
php artisan l5-swagger:generate
```
