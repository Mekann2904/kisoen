// テーマ設定
const themeColors = {
    'blue': '#0d6efd',
    'green': '#198754',
    'purple': '#6f42c1',
    'orange': '#fd7e14'
};

// 初期テーマ設定
let currentTheme = localStorage.getItem('theme') || 'blue';
applyTheme(currentTheme);

// テーマ適用関数
function applyTheme(theme) {
    document.documentElement.style.setProperty('--primary-color', themeColors[theme]);
    localStorage.setItem('theme', theme);
}

// テーマ選択UIの初期設定
const themeSelector = document.getElementById('theme-selector');
if (themeSelector) {
    themeSelector.value = currentTheme;
    themeSelector.addEventListener('change', (e) => {
        applyTheme(e.target.value);
        // ナビゲーションバーの色も更新
        document.querySelector('.navbar').classList.remove('bg-primary');
        document.querySelector('.navbar').classList.add('bg-primary');
    });
}

// グラフの初期設定
const ctx = document.getElementById('throughput-chart').getContext('2d');
let throughputChart;

// フォーム送信時の処理
document.getElementById('measurement-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // フォームデータの取得
    const formData = {
        targetAddress: document.getElementById('target-address').value,
        measurementCount: document.getElementById('measurement-count').value
    };

    try {
        // バックエンドにデータを送信
        const response = await fetch('measure.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        // 結果表示エリアを表示
        document.getElementById('results').style.display = 'block';

        // テーブルにデータを表示
        updateTable(data.results);

        // グラフを描画
        updateChart(data.results);

    } catch (error) {
        console.error('Error:', error);
        alert('計測中にエラーが発生しました');
    }
});

// テーブル更新関数
function updateTable(results) {
    const tbody = document.querySelector('#results-table tbody');
    tbody.innerHTML = '';

    results.forEach((result, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${result.throughput.toFixed(2)}</td>
            <td>${result.latency.toFixed(2)}</td>
        `;
        tbody.appendChild(row);
    });
}

// CSVエクスポート関数
function exportToCSV(results) {
    const headers = ['計測回数', 'スループット (Mbps)', '遅延 (ms)'];
    const csvContent = [
        headers.join(','),
        ...results.map((result, index) => [
            index + 1,
            result.throughput.toFixed(2),
            result.latency.toFixed(2)
        ].join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `measurement_results_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// エクスポートボタンの設定
document.getElementById('export-btn')?.addEventListener('click', () => {
    const results = Array.from(document.querySelectorAll('#results-table tbody tr')).map(row => ({
        throughput: parseFloat(row.children[1].textContent),
        latency: parseFloat(row.children[2].textContent)
    }));
    exportToCSV(results);
});

// グラフ更新関数
function updateChart(results) {
    const labels = results.map((_, index) => `計測 ${index + 1}`);
    const throughputData = results.map(result => result.throughput);

    if (throughputChart) {
        throughputChart.destroy();
    }

    throughputChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'スループット (Mbps)',
                data: throughputData,
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2
            }]
        },
        options: {
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
}