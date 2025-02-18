# API Documentation For Project Documentation Tool

## 1. Authentication APIs
### Register User
- **Endpoint:** `POST /api/register`
- **Content-Type:** `application/json`
**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password",
  "password_confirmation": "password"
}
```
### Login User
- **Endpoint:** `POST /api/login`
- **Content-Type:** `application/json`
**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password"
}
```
📌 Upon success, an access token is returned for authorization.

---
## 2. Project API Endpoints
### Get All Projects
- **Endpoint:** `GET /api/projects`
- **Authorization:** `Bearer <TOKEN>`

### Create a Project
- **Endpoint:** `POST /api/projects`
- **Authorization:** `Bearer <TOKEN>`
- **Content-Type:** `multipart/form-data`
**Request Body:**
```json
{
  "project_name": "New AI Project",
  "images": [file1.jpg, file2.jpg]
}
```
### Get a Single Project
- **Endpoint:** `GET /api/projects/{project_id}`
- **Authorization:** `Bearer <TOKEN>`

### Update a Project
- **Endpoint:** `POST /api/projects/update/{project_id}`
- **Authorization:** `Bearer <TOKEN>`
- **Content-Type:** `multipart/form-data`
**Request Body:**
```json
{
  "project_name": "Updated Project Name",
  "images": [new_image.jpg]
}
```
### Delete a Project
- **Endpoint:** `DELETE /api/projects/{project_id}`
- **Authorization:** `Bearer <TOKEN>`

---
## 3. AI-Powered Description APIs
### Generate Image Descriptions
- **Endpoint:** `POST /api/projects/{project_id}/generate-descriptions`
- **Authorization:** `Bearer <TOKEN>`
📌 **Expected Response:**
```json
{
  "ai_descriptions": {
    "uploads/projects/1/image1.jpg": "A beautiful mountain landscape with a clear blue sky.",
    "uploads/projects/1/image2.jpg": "A modern office workspace with a laptop on the desk."
  }
}
```
### Edit AI Descriptions
- **Endpoint:** `PUT /api/projects/{project_id}/edit-descriptions`
- **Authorization:** `Bearer <TOKEN>`
- **Content-Type:** `application/json`
**Request Body:**
```json
{
  "descriptions": {
    "uploads/projects/1/image1.jpg": "A snowy mountain view",
    "uploads/projects/1/image2.jpg": "An office desk with a laptop and books"
  }
}
```
### Generate Overall Descriptions
- **Endpoint:** `POST http://127.0.0.1:8000/api/projects/{project_id}/generate-overall-description`
- **Authorization:** `Bearer <TOKEN>`

### Generate Documentation
- **Endpoint:** `POST http://127.0.0.1:8000/api/projects/{project_id}/documentation/generate`
- **Authorization:** `Bearer <TOKEN>`

### Preview Documentation
- **Endpoint:** `GET http://127.0.0.1:8000/api/projects/{project_id}/documentation/preview`
- **Authorization:** `Bearer <TOKEN>`

### Update and Re-arrange Images
- **Endpoint:** `POST http://127.0.0.1:8000/api/projects/{project_id}/images`
- **Authorization:** `Bearer <TOKEN>`
- **Content-Type:** `application/json`
**Request Body:**
```json
{
  "images": [
    { "file_path": "https://example.com/image1.jpg", "description": "Front View", "order": 1 },
    { "file_path": "https://example.com/image2.jpg", "description": "Side View", "order": 3 },
    { "file_path": "https://example.com/image3.jpg", "description": "Back View", "order": 2 }
  ]
}
```
### Get Images
- **Endpoint:** `GET http://127.0.0.1:8000/api/projects/{project_id}/images`
- **Authorization:** `Bearer <TOKEN>`

### Get Project Details
- **Endpoint:** `GET http://127.0.0.1:8000/api/projects/{project_id}/getproject`
- **Authorization:** `Bearer <TOKEN>`
📌 **Expected Response:**
```json
{
  "project_id": 3,
  "project_name": "image upload project",
  "images": [
    "uploads/projects/3/oRagmFXdRm8MI5FisZthe4hsrcl8aBwOfn9eYGGa.png",
    "uploads/projects/3/INlYAtTopQubXfZD3fKquvPL4scVyPNyhIul7f7T.jpg",
    "uploads/projects/3/Zo5YoHpZErl7ACyYmAglrxwvh5YgzBvePRvUGJ3w.jpg"
  ]
}
```

---
## 4. Logout
### Endpoint
- **`POST /api/logout`**
- **Authorization:** `Bearer <TOKEN>`

---
This documentation summarizes the created APIs with clear endpoints, request formats, and sample responses for easy integration.

