<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import Season 3 match results (regular season only, no playoffs)';
    }

    public function up(Schema $schema): void
    {
        // Season 3
        $this->addSql(<<<'SQL'
            INSERT INTO season (name, start_date, end_date)
            SELECT 'Saison 3', '2026-01-01', '2026-06-22'
            WHERE NOT EXISTS (SELECT 1 FROM season WHERE name = 'Saison 3')
        SQL);

        // 5 divisions for Season 3
        $this->addSql(<<<'SQL'
            INSERT INTO division (name, season_id, is_finalized)
            SELECT d.name, s.id, true
            FROM (VALUES
                ('Division 1'),
                ('Division 2'),
                ('Division 3'),
                ('Division 4'),
                ('Division 5')
            ) AS d(name)
            JOIN season s ON s.name = 'Saison 3'
            WHERE NOT EXISTS (
                SELECT 1 FROM division div2
                JOIN season s2 ON s2.id = div2.season_id
                WHERE div2.name = d.name AND s2.name = 'Saison 3'
            )
        SQL);

        // Ensure game_status 'Terminé' exists
        $this->addSql(<<<'SQL'
            INSERT INTO game_status (name)
            SELECT 'Terminé'
            WHERE NOT EXISTS (SELECT 1 FROM game_status WHERE name = 'Terminé')
        SQL);

        // Teams (skip existing names)
        $this->addSql(<<<'SQL'
            INSERT INTO team (name)
            SELECT t.name FROM (VALUES
                ('Anarchy Academy'),
                ('Arowana'),
                ('ASC Kumo'),
                ('Booyah Gang'),
                ('Crimson Tide'),
                ('GoodLuck'),
                ('Hazard'),
                ('Inflamaska'),
                ('Ink Souls Maria'),
                ('Ink Souls Sina'),
                ('Jenova'),
                ('Last Roll'),
                ('Lumereis'),
                ('Lycoris'),
                ('Macalamar Šquad'),
                ('Mélodium'),
                ('MEOW!'),
                ('Mers Lunaires'),
                ('MoonUp'),
                ('Nozomi'),
                ('OctaBFsquad'),
                ('One Hit'),
                ('Oro Jackson'),
                ('Oyasumi'),
                ('Périhélie'),
                ('Pléiades'),
                ('Shake'),
                ('Shark Reef'),
                ('Splash Mirrors 3'),
                ('Splash-O''clock'),
                ('SplatDiamond Alpha'),
                ('SplatDiamond Oméga'),
                ('SUPER TEAM 5'),
                ('Team 7'),
                ('UBER BAGARRE !!'),
                ('ULTRA Rizens'),
                ('WIPEOUT !!')
            ) AS t(name)
            WHERE NOT EXISTS (SELECT 1 FROM team WHERE team.name = t.name)
        SQL);

        // Division 1 — 5 weeks × 3 matches = 15 games
        $this->addSql(<<<'SQL'
            INSERT INTO game (week, team1_id, team2_id, score1, score2, winner, status_id, division_id)
            SELECT g.week, t1.id, t2.id, g.score1, g.score2, g.winner, gs.id, d.id
            FROM (VALUES
                (1, 'Oro Jackson',     'Oyasumi',         4, 0, 1),
                (1, 'UBER BAGARRE !!', 'Hazard',          4, 0, 1),
                (1, 'Booyah Gang',     'Mélodium',        4, 0, 1),
                (2, 'Oro Jackson',     'Hazard',          4, 1, 1),
                (2, 'Oyasumi',         'Mélodium',        0, 4, 2),
                (2, 'UBER BAGARRE !!', 'Booyah Gang',     4, 3, 1),
                (3, 'Oro Jackson',     'Mélodium',        4, 2, 1),
                (3, 'Hazard',          'Booyah Gang',     0, 4, 2),
                (3, 'Oyasumi',         'UBER BAGARRE !!', 0, 4, 2),
                (4, 'Oro Jackson',     'Booyah Gang',     4, 1, 1),
                (4, 'Mélodium',        'UBER BAGARRE !!', 1, 4, 2),
                (4, 'Hazard',          'Oyasumi',         4, 1, 1),
                (5, 'Oro Jackson',     'UBER BAGARRE !!', 4, 0, 1),
                (5, 'Booyah Gang',     'Oyasumi',         4, 1, 1),
                (5, 'Mélodium',        'Hazard',          1, 4, 2)
            ) AS g(week, team1_name, team2_name, score1, score2, winner)
            JOIN team t1 ON t1.name = g.team1_name
            JOIN team t2 ON t2.name = g.team2_name
            JOIN game_status gs ON gs.name = 'Terminé'
            JOIN division d ON d.name = 'Division 1'
            JOIN season s ON s.id = d.season_id AND s.name = 'Saison 3'
        SQL);

        // Division 2 — 7 weeks × 4 matches = 28 games
        $this->addSql(<<<'SQL'
            INSERT INTO game (week, team1_id, team2_id, score1, score2, winner, status_id, division_id)
            SELECT g.week, t1.id, t2.id, g.score1, g.score2, g.winner, gs.id, d.id
            FROM (VALUES
                (1, 'MEOW!',           'Shark Reef',       4, 0, 1),
                (1, 'Inflamaska',      'Ink Souls Maria',  1, 4, 2),
                (1, 'WIPEOUT !!',      'Lumereis',         4, 0, 1),
                (1, 'Team 7',          'SUPER TEAM 5',     4, 2, 1),
                (2, 'MEOW!',           'Ink Souls Maria',  4, 0, 1),
                (2, 'Shark Reef',      'Lumereis',         1, 4, 2),
                (2, 'Inflamaska',      'SUPER TEAM 5',     4, 1, 1),
                (2, 'WIPEOUT !!',      'Team 7',           4, 3, 1),
                (3, 'MEOW!',           'Lumereis',         4, 1, 1),
                (3, 'Ink Souls Maria', 'SUPER TEAM 5',     4, 2, 1),
                (3, 'Shark Reef',      'Team 7',           1, 4, 2),
                (3, 'Inflamaska',      'WIPEOUT !!',       4, 2, 1),
                (4, 'MEOW!',           'SUPER TEAM 5',     4, 2, 1),
                (4, 'Lumereis',        'Team 7',           4, 1, 1),
                (4, 'Ink Souls Maria', 'WIPEOUT !!',       0, 4, 2),
                (4, 'Shark Reef',      'Inflamaska',       3, 4, 2),
                (5, 'MEOW!',           'Team 7',           4, 3, 1),
                (5, 'SUPER TEAM 5',    'WIPEOUT !!',       0, 4, 2),
                (5, 'Lumereis',        'Inflamaska',       4, 2, 1),
                (5, 'Ink Souls Maria', 'Shark Reef',       0, 4, 2),
                (6, 'MEOW!',           'WIPEOUT !!',       0, 4, 2),
                (6, 'Team 7',          'Inflamaska',       2, 4, 2),
                (6, 'SUPER TEAM 5',    'Shark Reef',       4, 0, 1),
                (6, 'Lumereis',        'Ink Souls Maria',  2, 4, 2),
                (7, 'MEOW!',           'Inflamaska',       4, 2, 1),
                (7, 'WIPEOUT !!',      'Shark Reef',       4, 0, 1),
                (7, 'Team 7',          'Ink Souls Maria',  3, 4, 2),
                (7, 'SUPER TEAM 5',    'Lumereis',         1, 4, 2)
            ) AS g(week, team1_name, team2_name, score1, score2, winner)
            JOIN team t1 ON t1.name = g.team1_name
            JOIN team t2 ON t2.name = g.team2_name
            JOIN game_status gs ON gs.name = 'Terminé'
            JOIN division d ON d.name = 'Division 2'
            JOIN season s ON s.id = d.season_id AND s.name = 'Saison 3'
        SQL);

        // Division 3 — 7 weeks × 4 matches = 28 games
        // One Hit forfeited repeatedly (score 0-4 or 4-0 depending on side)
        $this->addSql(<<<'SQL'
            INSERT INTO game (week, team1_id, team2_id, score1, score2, winner, status_id, division_id)
            SELECT g.week, t1.id, t2.id, g.score1, g.score2, g.winner, gs.id, d.id
            FROM (VALUES
                (1, 'Shake',            'Nozomi',           4, 0, 1),
                (1, 'One Hit',          'Last Roll',        0, 4, 2),
                (1, 'Splash Mirrors 3', 'Macalamar Šquad',  4, 1, 1),
                (1, 'Jenova',           'ASC Kumo',         4, 0, 1),
                (2, 'Shake',            'Last Roll',        4, 2, 1),
                (2, 'Nozomi',           'Macalamar Šquad',  2, 4, 2),
                (2, 'One Hit',          'ASC Kumo',         0, 4, 2),
                (2, 'Splash Mirrors 3', 'Jenova',           4, 3, 1),
                (3, 'Shake',            'Macalamar Šquad',  4, 0, 1),
                (3, 'Last Roll',        'ASC Kumo',         4, 2, 1),
                (3, 'Nozomi',           'Jenova',           0, 4, 2),
                (3, 'One Hit',          'Splash Mirrors 3', 0, 4, 2),
                (4, 'Shake',            'ASC Kumo',         2, 4, 2),
                (4, 'Macalamar Šquad',  'Jenova',           1, 4, 2),
                (4, 'Last Roll',        'Splash Mirrors 3', 0, 4, 2),
                (4, 'Nozomi',           'One Hit',          4, 0, 1),
                (5, 'Shake',            'Jenova',           0, 4, 2),
                (5, 'ASC Kumo',         'Splash Mirrors 3', 1, 4, 2),
                (5, 'Macalamar Šquad',  'One Hit',          4, 0, 1),
                (5, 'Last Roll',        'Nozomi',           4, 3, 1),
                (6, 'Shake',            'Splash Mirrors 3', 1, 4, 2),
                (6, 'Jenova',           'One Hit',          4, 0, 1),
                (6, 'ASC Kumo',         'Nozomi',           4, 0, 1),
                (6, 'Macalamar Šquad',  'Last Roll',        4, 0, 1),
                (7, 'Shake',            'One Hit',          4, 0, 1),
                (7, 'Splash Mirrors 3', 'Nozomi',           4, 0, 1),
                (7, 'Jenova',           'Last Roll',        4, 0, 1),
                (7, 'ASC Kumo',         'Macalamar Šquad',  4, 1, 1)
            ) AS g(week, team1_name, team2_name, score1, score2, winner)
            JOIN team t1 ON t1.name = g.team1_name
            JOIN team t2 ON t2.name = g.team2_name
            JOIN game_status gs ON gs.name = 'Terminé'
            JOIN division d ON d.name = 'Division 3'
            JOIN season s ON s.id = d.season_id AND s.name = 'Saison 3'
        SQL);

        // Division 4 — 7 weeks × 4 matches = 28 games
        $this->addSql(<<<'SQL'
            INSERT INTO game (week, team1_id, team2_id, score1, score2, winner, status_id, division_id)
            SELECT g.week, t1.id, t2.id, g.score1, g.score2, g.winner, gs.id, d.id
            FROM (VALUES
                (1, 'GoodLuck',           'Mers Lunaires',      4, 2, 1),
                (1, 'Périhélie',          'SplatDiamond Oméga', 4, 1, 1),
                (1, 'SplatDiamond Alpha', 'ULTRA Rizens',       4, 0, 1),
                (1, 'Pléiades',           'Crimson Tide',       4, 3, 1),
                (2, 'GoodLuck',           'SplatDiamond Oméga', 4, 0, 1),
                (2, 'Mers Lunaires',      'ULTRA Rizens',       4, 0, 1),
                (2, 'Périhélie',          'Crimson Tide',       4, 0, 1),
                (2, 'SplatDiamond Alpha', 'Pléiades',           4, 0, 1),
                (3, 'GoodLuck',           'ULTRA Rizens',       4, 0, 1),
                (3, 'SplatDiamond Oméga', 'Crimson Tide',       1, 4, 2),
                (3, 'Mers Lunaires',      'Pléiades',           4, 0, 1),
                (3, 'Périhélie',          'SplatDiamond Alpha', 4, 1, 1),
                (4, 'GoodLuck',           'Crimson Tide',       4, 0, 1),
                (4, 'ULTRA Rizens',       'Pléiades',           0, 4, 2),
                (4, 'SplatDiamond Oméga', 'SplatDiamond Alpha', 0, 4, 2),
                (4, 'Mers Lunaires',      'Périhélie',          0, 4, 2),
                (5, 'GoodLuck',           'Pléiades',           1, 4, 2),
                (5, 'Crimson Tide',       'SplatDiamond Alpha', 1, 4, 2),
                (5, 'ULTRA Rizens',       'Périhélie',          0, 4, 2),
                (5, 'SplatDiamond Oméga', 'Mers Lunaires',      1, 4, 2),
                (6, 'GoodLuck',           'SplatDiamond Alpha', 4, 2, 1),
                (6, 'Pléiades',           'Périhélie',          0, 4, 2),
                (6, 'Crimson Tide',       'Mers Lunaires',      0, 4, 2),
                (6, 'ULTRA Rizens',       'SplatDiamond Oméga', 0, 4, 2),
                (7, 'GoodLuck',           'Périhélie',          0, 4, 2),
                (7, 'SplatDiamond Alpha', 'Mers Lunaires',      4, 2, 1),
                (7, 'Pléiades',           'SplatDiamond Oméga', 0, 4, 2),
                (7, 'Crimson Tide',       'ULTRA Rizens',       4, 0, 1)
            ) AS g(week, team1_name, team2_name, score1, score2, winner)
            JOIN team t1 ON t1.name = g.team1_name
            JOIN team t2 ON t2.name = g.team2_name
            JOIN game_status gs ON gs.name = 'Terminé'
            JOIN division d ON d.name = 'Division 4'
            JOIN season s ON s.id = d.season_id AND s.name = 'Saison 3'
        SQL);

        // Division 5 — 7 weeks, 3 matches/week (only 6 teams) = 21 games
        $this->addSql(<<<'SQL'
            INSERT INTO game (week, team1_id, team2_id, score1, score2, winner, status_id, division_id)
            SELECT g.week, t1.id, t2.id, g.score1, g.score2, g.winner, gs.id, d.id
            FROM (VALUES
                (1, 'MoonUp',          'OctaBFsquad',      4, 0, 1),
                (1, 'Ink Souls Sina',  'Arowana',          0, 4, 2),
                (1, 'Splash-O''clock', 'Anarchy Academy',  4, 0, 1),
                (2, 'Lycoris',         'OctaBFsquad',      4, 0, 1),
                (2, 'MoonUp',          'Anarchy Academy',  1, 4, 2),
                (2, 'Ink Souls Sina',  'Splash-O''clock',  1, 4, 2),
                (3, 'Lycoris',         'Arowana',          4, 2, 1),
                (3, 'OctaBFsquad',     'Anarchy Academy',  0, 4, 2),
                (3, 'MoonUp',          'Ink Souls Sina',   4, 0, 1),
                (4, 'Lycoris',         'Anarchy Academy',  4, 0, 1),
                (4, 'Arowana',         'Splash-O''clock',  4, 1, 1),
                (4, 'OctaBFsquad',     'Ink Souls Sina',   0, 4, 2),
                (5, 'Lycoris',         'Splash-O''clock',  4, 2, 1),
                (5, 'Anarchy Academy', 'Ink Souls Sina',   4, 0, 1),
                (5, 'Arowana',         'MoonUp',           4, 1, 1),
                (6, 'Lycoris',         'Ink Souls Sina',   4, 0, 1),
                (6, 'Splash-O''clock', 'MoonUp',           4, 0, 1),
                (6, 'Arowana',         'OctaBFsquad',      4, 0, 1),
                (7, 'Lycoris',         'MoonUp',           4, 2, 1),
                (7, 'Splash-O''clock', 'OctaBFsquad',      4, 0, 1),
                (7, 'Anarchy Academy', 'Arowana',          1, 4, 2)
            ) AS g(week, team1_name, team2_name, score1, score2, winner)
            JOIN team t1 ON t1.name = g.team1_name
            JOIN team t2 ON t2.name = g.team2_name
            JOIN game_status gs ON gs.name = 'Terminé'
            JOIN division d ON d.name = 'Division 5'
            JOIN season s ON s.id = d.season_id AND s.name = 'Saison 3'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM game
            WHERE division_id IN (
                SELECT d.id FROM division d
                JOIN season s ON s.id = d.season_id
                WHERE s.name = 'Saison 3'
            )
        SQL);

        $this->addSql(<<<'SQL'
            DELETE FROM division
            WHERE season_id = (SELECT id FROM season WHERE name = 'Saison 3')
        SQL);

        $this->addSql("DELETE FROM season WHERE name = 'Saison 3'");
    }
}
