# Coderium
In this project I want to create a web that Call Coderium

## Goals:
1. Coderium will show the post/contents like instagram
2. User can create a post in the admin panel, the post will have article, carousel image or video
3. User can create a play list or folders, it will contain the posts that put in this play list
4. This site should have good SEO, because we can share the post in this web

## Page:
1. Public Home, it containts play list, recent posts (article, carousel, video)
2. Public Post Detail, it containts the detail of the post, It can show the carousel image or video
3. Public Search Post, user can search the article, carousel image or video
4. Admin Dashboard, it containts the analytics of the coderium sites
5. Admin Play List, it containts the all play list that has been created by users, admin can see all the posts that has been put in this play list.
6. Admin Post List, it containts the all posts (article, carousel images, and videos)
7. For the Admin play List, admin can create, edit, delete, and for see the detail just view the public post detail.

## Relational Database:
### Play List
1. ID
2. Title
3. Description
4. Slug
5. Cover
6. User ID (FK)

### Posts
1. ID
2. Slug
3. Title
4. Subtitle
5. Content
6. Tags
7. Cover
8. User ID (FK)

### Posts Play List
1. ID
2. Post ID (FK)
3. Play List ID (FK)
4. User ID (FK)

### Posts Media
1. ID
2. File Path
2. Post ID (FK)

### Medias
1. ID
2. File Name
4. Path
5. Size
6. Type

### Tech Stack:
1. Frontend Vue
2. Backend Laravel
3. Database MySQL
4. Laravel + Inertia.js

## Requirement
1. AI agent must be create the Backend and the Frontend
