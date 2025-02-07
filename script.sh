#!/bin/bash

if [ "$#" -ne 2 ]; then
    echo "Usage: $0 <IP_ADDRESS> <NUM_RUNS>"
    exit 1
fi

IP_ADDRESS=$1
NUM_RUNS=$2

# JSON配列開始
echo '{"runs":['

for ((i=1; i<=NUM_RUNS; i++)); do
    #-----------------------------------------
    # iperf実行
    #-----------------------------------------
    iperf_result=$(iperf -c "$IP_ADDRESS" -y C -t 3 2>/dev/null)
    if [ -z "$iperf_result" ]; then
        echo "Error: iperf failed for run $i" >&2
        continue
    fi

    # CSV形式の解析
    IFS=',' read -r date cl_ip cl_port srv_ip srv_port id interval transfer bitrate <<< "$iperf_result"

    #-----------------------------------------
    # ping実行
    #-----------------------------------------
    ping_result=$(ping -c 1 -n "$IP_ADDRESS" 2>/dev/null)
    latency="N/A"
    if [[ "$ping_result" =~ time=([0-9\.]+)\ ms ]]; then
        latency="${BASH_REMATCH[1]}"
    fi

    #-----------------------------------------
    # JSONフォーマットで結果を出力
    #-----------------------------------------
    echo -n "{\"run\":$i,\"interval\":\"$interval\",\"transfer\":\"$transfer\",\"bitrate\":\"$bitrate\",\"latency\":\"$latency\"}"

    # 最後以外はカンマを追加
    if [ "$i" -ne "$NUM_RUNS" ]; then
        echo ","
    fi
done

# JSONを閉じる
echo ']}'
