🖥️ Frontend Installation (Vue 3 + Vite)
1. Navigate to frontend
cd frontend

2. Install dependencies
npm install

3. Configure environment variables

# env 
copy .env.example rename ..env.development.local


# install core

npm config set @nong-official-dev:registry https://npm.pkg.github.com
npm config set //npm.pkg.github.com/:_authToken YOUR_GITHUB_TOKEN

npm install @nong-official-dev/core

VITE_API_URL=http://localhost:8000/api

4. Start the development server
npm run dev


0001_01_01_000000_create_users_table