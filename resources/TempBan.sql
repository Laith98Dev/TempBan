-- #!sqlite
-- #{tempban
-- #  {init
CREATE TABLE IF NOT EXISTS banPlayers ( 
  player VARCHAR(30) NOT NULL PRIMARY KEY,
  banTime INT(11) NOT NULL,
  reason VARCHAR(30) NOT NULL,
  staff VARCHAR(30) NOT NULL
);
-- #  }
-- #  {get-ban-info
-- #     :player string
SELECT * FROM banPlayers WHERE player = :player;
-- #  }
-- #  {get-all-bans
SELECT * FROM banPlayers;
-- #  }
-- #  {ban-player
-- #     :player string
-- #     :banTime int
-- #     :reason string
-- #     :staff string
INSERT OR REPLACE INTO banPlayers(player, banTime, reason, staff) VALUES ( :player , :banTime, :reason, :staff);
-- #  }
-- #  {unban-player
-- #     :player string
DELETE FROM banPlayers WHERE player = :player;
-- #  }
-- #}
