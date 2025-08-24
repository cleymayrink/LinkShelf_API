# LinkShelf API

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
</p>

This is the robust and intelligent backend API for **LinkShelf**, a modern web application for saving, organizing, and searching your links. Built with Laravel 8, the API provides secure authentication, comprehensive data management, and AI-powered features to automatically enrich saved content.

---

## ✨ Key Features

* **Secure Authentication**: Complete user registration and login system using **Laravel Sanctum** for token-based API authentication.
* **Automatic Metadata Fetching**: When a URL is submitted, the API automatically scrapes:
    * The page title (using `og:title` or the `<title>` tag).
    * A preview image (`og:image`, `twitter:image`, etc.).
    * Relevant text content from the page, removing boilerplate like scripts, menus, and footers.
* **AI Enrichment (Google Gemini)**:
    * **Automatic Summarization**: The extracted text content is sent to the Google Gemini API to generate a concise and intelligent summary of the link.
    * **Tag Suggestions**: The AI also analyzes the content and suggests 3-5 relevant tags, facilitating automatic categorization.
* **Full CRUD Functionality**:
    * **Links**: Create, read, update, and delete links.
    * **Folders**: Organize your links into folders with custom icons and colors.
    * **Tags**: Create and associate multiple tags with links and folders for flexible organization.
* **Unified Search**: A powerful search endpoint that queries across link titles, summaries, folder names, and associated tags.

## 🚀 Frontend Application

The official client for this API is the **[LinkShelf React App](https://github.com/cleymayrink/LinkShelf)**.

## 🛠️ Tech Stack

* **Framework**: Laravel 8
* **Authentication**: Laravel Sanctum (API Tokens)
* **Database**: PostgreSQL (configured for Supabase in `.env.example`)
* **HTML Processing**: Symfony DOM Crawler
* **Artificial Intelligence**: Google Gemini (for summaries and tags)
* **HTTP Client**: Guzzle HTTP

## 🔑 API Endpoints

All endpoints are prefixed with `/api`. Authentication is required for all routes except `/register` and `/login`.

### Authentication

| Method | Endpoint  | Description                                  |
| :----- | :-------- | :------------------------------------------- |
| `POST` | `/register` | Registers a new user.                        |
| `POST` | `/login`  | Authenticates a user and returns a Sanctum token. |
| `GET`  | `/user`   | Returns the authenticated user's data.       |
| `POST` | `/logout` | Invalidates the user's authentication token. |

### Links

| Method   | Endpoint        | Description                                                 |
| :------- | :-------------- | :---------------------------------------------------------- |
| `GET`    | `/links`        | Lists all links for the user. Accepts `?folder_id=<id>` for filtering. |
| `POST`   | `/links`        | Creates a new link from a URL (with metadata and AI processing). |
| `PUT`    | `/links/{link}` | Updates a link's title, summary, and tags.                  |
| `DELETE` | `/links/{link}` | Deletes a link.                                             |

### Folders

| Method   | Endpoint         | Description                   |
| :------- | :--------------- | :---------------------------- |
| `GET`    | `/folders`       | Lists all of the user's folders. |
| `POST`   | `/folders`       | Creates a new folder.         |
| `GET`    | `/folders/{id}`  | Shows a specific folder's data. |
| `PUT`    | `/folders/{id}`  | Updates a folder.             |
| `DELETE` | `/folders/{id}`  | Deletes a folder.             |

### Tags

| Method | Endpoint | Description              |
| :----- | :------- | :----------------------- |
| `GET`  | `/tags`  | Lists all available tags. |

### Search

| Method | Endpoint  | Description                                     |
| :----- | :-------- | :---------------------------------------------- |
| `GET`  | `/search` | Searches links and folders. Use `?q=<term>`.      |

## 🏁 Getting Started (Local Development)

Follow the steps below to get a local copy of the project up and running.

### Prerequisites

* PHP 8.0+
* Composer
* PostgreSQL (or another database of your choice)

### Installation

1.  **Clone the repository:**
    ```sh
    git clone [https://github.com/cleymayrink/LinkShelf_API.git](https://github.com/cleymayrink/LinkShelf_API.git)
    cd LinkShelf_API
    ```

2.  **Install dependencies:**
    ```sh
    composer install
    ```

3.  **Set up environment variables:**

    Copy the `.env.example` file to `.env` and configure your variables, especially for the database and Gemini API key.
    ```sh
    cp .env.example .env
    ```
    **`.env` file:**
    ```env
    DB_CONNECTION=pgsql
    DB_HOST=aws-0-sa-east-1.pooler.supabase.com
    DB_PORT=6543
    DB_DATABASE=postgres
    DB_USERNAME=postgres.mpezlnlqlezpzubqdlfj
    DB_PASSWORD=your_supabase_password # Replace with your password

    # Add your Google Gemini API Key
    GEMINI_API_KEY=YOUR_KEY_HERE
    ```

4.  **Generate the application key:**
    ```sh
    php artisan key:generate
    ```

5.  **Run database migrations:**
    ```sh
    php artisan migrate
    ```

6.  **Start the development server:**
    ```sh
    php artisan serve
    ```
    The API will be available at `http://localhost:8000`.

## 🐳 Running with Docker

The project includes a `Dockerfile` for easy containerization.

1.  **Build the Docker image:**
    ```sh
    docker build -t linkshelf-api .
    ```

2.  **Run the container:**
    ```sh
    docker run -p 8080:80 \
      -e DB_CONNECTION=pgsql \
      -e DB_HOST=<your-host> \
      -e DB_DATABASE=<your-db> \
      -e DB_USERNAME=<your-user> \
      -e DB_PASSWORD=<your-password> \
      -e GEMINI_API_KEY=<your-key> \
      linkshelf-api
    ```
    The API will be available at `http://localhost:8080`.

## 📄 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.
