(() => {
    const rankingBody = document.getElementById('rankingBody');

    if (!rankingBody) {
        return;
    }

    async function refreshRanking() {
        try {
            const data = await window.HangmanUI.requestJSON('api/ranking_data.php');

            if (!data.success || !Array.isArray(data.ranking)) {
                return;
            }

            rankingBody.innerHTML = data.ranking
                .map((row, index) => {
                    const winrate = Number(row.winrate || 0).toFixed(2);
                    const username = window.HangmanUI.escapeHTML(row.username || '');

                    return `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${username}</td>
                            <td>${row.trofeos}</td>
                            <td>${winrate}%</td>
                        </tr>
                    `;
                })
                .join('');
        } catch (error) {
            console.error(error.message);
        }
    }

    refreshRanking();
    setInterval(refreshRanking, 5000);
})();
