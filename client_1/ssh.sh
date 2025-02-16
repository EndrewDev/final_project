#!/bin/bash

# Verifica se a porta 80 está em uso
PORT=80
SERVICE=$(sudo lsof -i :$PORT | awk 'NR==2 {print $1}')

if [ -z "$SERVICE" ]; then
    echo "A porta $PORT está livre. Iniciando o Docker Compose..."
    docker compose up
else
    echo "A porta $PORT está ocupada pelo serviço: $SERVICE"
    read -p "Deseja parar o serviço $SERVICE para liberar a porta? (s/n): " RESPONSE
    if [[ "$RESPONSE" =~ ^[Ss]$ ]]; then
        echo "Parando o serviço $SERVICE..."
        sudo systemctl stop "$SERVICE.service"
        if [ $? -eq 0 ]; then
            echo "Serviço $SERVICE parado com sucesso. Iniciando o Docker Compose..."
            docker compose down
            docker compose up
        else
            echo "Falha ao parar o serviço $SERVICE. Verifique manualmente."
            exit 1
        fi
    else
        echo "O serviço $SERVICE não foi parado. Script encerrado."
        exit 1
    fi
fi