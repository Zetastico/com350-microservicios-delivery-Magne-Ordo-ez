FROM node:24-alpine

WORKDIR /app

# Dependencias
COPY package*.json ./
RUN npm install --omit=dev

# Código
COPY . .

EXPOSE 3004

CMD ["npm", "start"]