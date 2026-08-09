#!/bin/bash

# Test Animapper endpoints with curl
# Run: bash test-animapper-curl.sh

echo "====== Animapper API Test (curl) ======"
echo

echo "=== 1. Metadata (id=1) ==="
echo "URL: https://api.animapper.net/api/v1/metadata?id=1"
echo
curl -sS 'https://api.animapper.net/api/v1/metadata?id=1' | python3 -m json.tool | head -50
echo
echo "..."
echo

echo "=== 2. Episodes (id=1, provider=ANIMEVIETSUB) ==="
echo "URL: https://api.animapper.net/api/v1/stream/episodes?id=1&provider=ANIMEVIETSUB"
echo
curl -sS 'https://api.animapper.net/api/v1/stream/episodes?id=1&provider=ANIMEVIETSUB' | python3 -m json.tool
echo

echo "=== 3. Stream Source (episodeData=a820\$13339, provider=ANIMEVIETSUB, server=DU) ==="
echo "URL: https://api.animapper.net/api/v1/stream/source?episodeData=a820\$13339&provider=ANIMEVIETSUB&server=DU"
echo
curl -sS 'https://api.animapper.net/api/v1/stream/source?episodeData=a820$13339&provider=ANIMEVIETSUB&server=DU' | python3 -m json.tool
echo

echo "=== 4. Stream Source HDX (episodeData=a820\$13339, provider=ANIMEVIETSUB, server=HDX) ==="
echo "URL: https://api.animapper.net/api/v1/stream/source?episodeData=a820\$13339&provider=ANIMEVIETSUB&server=HDX"
echo
curl -sS 'https://api.animapper.net/api/v1/stream/source?episodeData=a820$13339&provider=ANIMEVIETSUB&server=HDX' | python3 -m json.tool
