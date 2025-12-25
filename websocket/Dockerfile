FROM node:20-alpine

WORKDIR /app

# Install dependencies for sed
RUN apk add --no-cache bash

COPY package.json ./
RUN npm install

COPY . .

# Make start script executable
RUN chmod +x start.sh

EXPOSE 6001

CMD ["sh", "start.sh"]

