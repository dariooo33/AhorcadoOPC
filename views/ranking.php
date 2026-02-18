<section class="panel">
    <div class="panel-head">
        <h1>Ranking global</h1>
        <span class="chip">Actualizacion automatica</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario</th>
                    <th>Trofeos</th>
                    <th>Winrate</th>
                </tr>
            </thead>
            <tbody id="rankingBody">
                <?php foreach ($ranking as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e((string) $row['username']) ?></td>
                        <td><?= (int) $row['trofeos'] ?></td>
                        <td><?= number_format((float) $row['winrate'], 2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
