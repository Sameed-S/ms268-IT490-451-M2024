# Project Summary

## Project Goal
This web-based project aims to provide users with a diverse collection of recipes, enabling them to browse, search, and bookmark their favorites. Each recipe will include comprehensive details such as ingredients and step-by-step instructions for dish preparation. Additionally, the website will feature a 'Meal of the Day' section, presenting a randomly selected meal for users to explore. This feature will offer filtering options, allowing users to specify meal preferences based on the type of meat.

## Features
### Core Requirements
- Users can register, login, update their profile, and logout (with appropriate session termination).
- Passwords are securely hashed, not stored in plaintext.
- Users have different roles:
  - Admin: Can modify system data through the web UI.
### Project Requirements
- Users have different roles, including:
  - Admin: Can perform special tasks not available to other users.
- Ability to trigger a separate API request to refresh API data based on criteria such as idmeal or main ingredient.
### Client
- Common user can:
  - Favorite/unfavorite recipes.
  - View their user profile, which includes their username, first and last name, email, and a link to the favorites page.
- Search feature will search through a local cached DB, with the ability to filter by origin, name, or type of ingredient.
- Dedicated page for users to view favorited recipes, sorted alphabetically, with options to view detailed information.
- Randomized meal of the day feature, customizable with a user-side filter.
### Recipe Management
- Create/edit recipes with image URLs, ingredients, instructions, and category selection.
- Ability to hard delete recipes.
- Recipe instance view displays a picture of the dish, ingredients, instructions, and rating.
### API Use Case
- Periodic checks for updates on API data.
- Fetch recipes and store them in the DB, able to pass search queries to the API and filter recipes by origin, name, or main ingredient.

## Requirements
- All app data requests will go through MQ.
- The app will not directly connect to the DB server or API server.
- VM logs should be sent to a central location.
- Midterm: Core project features should be implemented.
- Final: Multi-lane deployment system with custom migration scripts, zero-downtime deployment strategy, and VM monitoring.
