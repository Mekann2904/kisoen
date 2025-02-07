<?php
/*************************************************************
 * iperf計測アプリ (index.php)
 *
 * [動作フロー]
 *   1) POST(Ajax) で "ip", "runs" を受け取り script.sh 実行 → JSON 返す
 *   2) GET でアクセスされた場合: このファイル内の HTML を表示
 *************************************************************/

// (必要に応じてエラー表示)
// error_reporting(E_ALL);
// ini_set("display_errors", 1);

ini_set('max_execution_time', 300);  // 5分

// ---------- (A) Ajax POST -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ip']) && isset($_POST['runs'])) {
    $ip   = trim($_POST['ip']);
    $runs = trim($_POST['runs']);

    // 入力チェック
    if ($ip === '' || !ctype_digit($runs) || (int)$runs < 1) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'IPまたは回数の指定が不正です。']);
        exit;
    }

    // script.sh 実行
    $command = "./script.sh " . escapeshellarg($ip) . " " . escapeshellarg($runs);
    $output  = shell_exec($command);

    // JSONパース
    $json_data = @json_decode($output, true);
    if (!$json_data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'iperf実行結果が取得できませんでした。']);
        exit;
    }

    // 正常応答
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($json_data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- (B) GET: HTMLを返す ----------
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <title>ネットワーク計測アプリ</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- Bootstrap / Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
  />

  <style>
    /* テーマクラス: .navbar.bg-primary の色, ボタン色を変える */
    .blue-theme .navbar.bg-primary {
      background-color: #0d6efd !important;
    }
    .blue-theme {
      --bs-primary: #0d6efd !important;
      --bs-btn-bg:  #0d6efd !important;
    }

    .green-theme .navbar.bg-primary {
      background-color: #198754 !important;
    }
    .green-theme {
      --bs-primary: #198754 !important;
      --bs-btn-bg:  #198754 !important;
    }

    .purple-theme .navbar.bg-primary {
      background-color: #6f42c1 !important;
    }
    .purple-theme {
      --bs-primary: #6f42c1 !important
      --bs-btn-bg:  #6f42c1 !important;
    }

    .orange-theme .navbar.bg-primary {
      background-color: #fd7e14 !important;
    }
    .orange-theme {
      --bs-primary: #fd7e14 !important;
      --bs-btn-bg:  #fd7e14 !important;
    }

    /* スピナーを隠す/表示 */
    #loading-spinner {
      display: none;
    }

    /* JSON表示用テキストエリア */
    #raw-json {
      font-size: 0.9rem;
      background-color: #f8f9fa;
      color: #333;
    }
  </style>
</head>
<body class="bg-light">

  <!-- ナビゲーションバー -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand" href="#">ネットワーク計測アプリ</a>
      <div class="ms-auto">
        <!-- テーマ切り替え -->
        <select class="form-select form-select-sm" id="theme-selector">
          <option value="blue" selected>Blue Theme</option>
          <option value="green">Green Theme</option>
          <option value="purple">Purple Theme</option>
          <option value="orange">Orange Theme</option>
        </select>
      </div>
    </div>
  </nav>

  <main class="container my-5">

    <!-- パラメータ設定フォーム -->
    <div class="card shadow">
      <div class="card-header bg-white">
        <h2 class="h4 mb-0">計測パラメータ設定</h2>
      </div>
      <div class="card-body">
        <form id="measurement-form" class="needs-validation" novalidate>
          <div class="mb-3">
            <label for="target-address" class="form-label">宛先アドレス (IP)</label>
            <input type="text" class="form-control" id="target-address" required />
            <div class="invalid-feedback">宛先アドレスを入力してください</div>
          </div>
          <div class="mb-4">
            <label for="measurement-count" class="form-label">計測回数</label>
            <input type="number" class="form-control" id="measurement-count" min="1" required />
            <div class="invalid-feedback">1回以上の計測回数を指定してください</div>
          </div>
          <button type="submit" class="btn btn-primary w-100">計測開始</button>
        </form>
      </div>
    </div>

    <!-- 計測中スピナー -->
    <div id="loading-spinner" class="text-center my-3">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">計測中...</span>
      </div>
      <p class="mt-2">計測中です。しばらくお待ちください...</p>
    </div>

    <!-- 計測結果表示カード -->
    <div class="card shadow mt-4" id="results" style="display: none;">
      <div class="card-header bg-white">
        <h2 class="h4 mb-0">計測結果</h2>
      </div>
      <div class="card-body">
        <!-- JSON生データ表示 -->
        <div class="mb-3">
          <label class="form-label">取得したJSON</label>
          <textarea id="raw-json" class="form-control" rows="5" readonly></textarea>
        </div>

        <!-- CSVエクスポートボタン -->
        <div class="d-flex justify-content-end mb-3">
          <button id="export-btn" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download"></i> CSVでエクスポート
          </button>
        </div>

        <!-- テーブル＆グラフ -->
        <div class="row">
          <div class="col-md-6 mb-4 mb-md-0">
            <div class="table-responsive">
              <table class="table table-striped" id="results-table">
                <thead>
                  <tr>
                    <th>計測回数</th>
                    <th>スループット (Mbps)</th>
                    <th>遅延 (ms)</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- JSで追加 -->
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-6">
            <canvas id="throughput-chart"></canvas>
          </div>
        </div>

        <hr />
        <!-- 平均スループット表示 -->
        <div class="mb-3">
          <h5>平均スループット: <span id="average-throughput">--</span> Mbps</h5>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-white py-3 border-top">
    <div class="container text-center">
      <p class="mb-0 text-muted small">&copy; 2025 ネットワーク計測アプリ</p>
    </div>
  </footer>

  <!-- Bootstrap / Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // ========== (1) Bootstrapフォームバリデーション ==========
    (function () {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();

    // ========== (2) テーマ切り替え(ボタン色含む) ==========
    const themeSelector = document.getElementById('theme-selector');
    themeSelector.addEventListener('change', () => {
      // 他のテーマクラスを削除
      document.body.classList.remove('blue-theme','green-theme','purple-theme','orange-theme');
      // 選択したテーマクラスを付与
      const theme = themeSelector.value;
      document.body.classList.add(theme + '-theme');
    });

    // ========== (3) 各種要素 ==========
    const measurementForm = document.getElementById('measurement-form');
    const loadingSpinner = document.getElementById('loading-spinner');
    const resultsCard    = document.getElementById('results');
    const rawJsonArea    = document.getElementById('raw-json');
    const resultsTableBody = document.getElementById('results-table').querySelector('tbody');
    const averageThroughputElem = document.getElementById('average-throughput');
    const throughputChartCanvas = document.getElementById('throughput-chart');
    const exportBtn = document.getElementById('export-btn');

    let throughputChart = null; // Chart.js

    // ========== (4) フォーム送信 => script.sh 実行 => JSON ========== 
    measurementForm.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!measurementForm.checkValidity()) {
        return;
      }

      // スピナーON, 結果OFF
      loadingSpinner.style.display = 'block';
      resultsCard.style.display    = 'none';

      const ip   = document.getElementById('target-address').value;
      const runs = document.getElementById('measurement-count').value;

      fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ip, runs }),
      })
        .then(res => res.json())
        .then(data => {
          if (data.error) {
            alert('エラー: ' + data.error);
            return;
          }
          // JSON全文をテキストエリアに表示
          rawJsonArea.value = JSON.stringify(data, null, 2);

          // data = { runs: [ { run, interval, transfer, bitrate, latency }, ... ] }
          displayResults(data.runs);
        })
        .catch(err => {
          console.error('通信エラー:', err);
          alert('計測で通信エラーが発生しました。');
        })
        .finally(() => {
          loadingSpinner.style.display = 'none';
          resultsCard.style.display    = 'block';
        });
    });

    // ========== (5) 表 & グラフ表示 ==========
    function displayResults(runs) {
      resultsTableBody.innerHTML = '';
      let sum = 0;

      runs.forEach(item => {
        // "bitrate" が "78492431" 等の場合 -> bps として変換
        const throughputMbps = parseBitrateToMbps(item.bitrate);

        // latency
        const latencyVal = item.latency || 'N/A';

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${item.run}</td>
          <td>${throughputMbps.toFixed(2)}</td>
          <td>${latencyVal}</td>
        `;
        resultsTableBody.appendChild(tr);

        if (!isNaN(throughputMbps)) {
          sum += throughputMbps;
        }
      });

      const avg = runs.length ? sum / runs.length : 0;
      averageThroughputElem.textContent = avg.toFixed(2);

      // グラフ更新
      updateChart(runs);
    }

    // ========== (6) bps -> Mbps 変換 ==========
    function parseBitrateToMbps(str) {
      if (!str) return 0;
      const val = parseFloat(str);
      if (isNaN(val)) return 0;
      if (str.includes('Kbits/sec')) {
        return val / 1000;
      } else if (str.includes('Mbits/sec')) {
        return val;
      } else if (str.includes('Gbits/sec')) {
        return val * 1000;
      } else {
        // 単位なし (bps)
        return val / 1e6;
      }
    }

    // ========== (7) Chart.js グラフ ==========
    function updateChart(runs) {
      const labels = runs.map(r => '計測' + r.run);
      const data   = runs.map(r => parseBitrateToMbps(r.bitrate));

      if (!throughputChart) {
        // 初回作成
        throughputChart = new Chart(throughputChartCanvas, {
          type: 'line',
          data: {
            labels,
            datasets: [{
              label: 'スループット (Mbps)',
              data,
              borderColor: 'rgba(75,192,192,1)',
              borderWidth: 2,
              tension: 0.1
            }]
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true,
                title: {
                  display: true,
                  text: 'Mbps'
                }
              }
            }
          }
        });
      } else {
        // 更新
        throughputChart.data.labels = labels;
        throughputChart.data.datasets[0].data = data;
        throughputChart.update();
      }
    }

    // ========== (8) CSVエクスポート ==========
    exportBtn.addEventListener('click', () => {
      const table = document.getElementById('results-table');
      if (!table) return;

      let csvContent = '計測回数,スループット(Mbps),遅延(ms)\n';
      const rows = table.querySelectorAll('tbody tr');
      rows.forEach(tr => {
        const tds = tr.querySelectorAll('td');
        const rowData = [...tds].map(td => td.innerText).join(',');
        csvContent += rowData + '\n';
      });

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url  = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href  = url;
      link.download = 'measurement_results.csv';
      link.click();
    });
  </script>
</body>
</html>
