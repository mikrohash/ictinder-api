
DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`database`@`localhost` PROCEDURE `PEERS` (IN `node_id` INT)  NO SQL
  DETERMINISTIC
SELECT node1+node2-node_id AS id FROM peering WHERE node1 = node_id || node2 = node_id$$

CREATE DEFINER=`database`@`localhost` PROCEDURE `UNPEERS` (IN `node_id` INT)  NO SQL
  DETERMINISTIC
SELECT node_issue+node_complaining-node_id AS id FROM unpeering WHERE node_issue = node_id || node_complaining = node_id$$

--
-- Functions
--
CREATE DEFINER=`database`@`localhost` FUNCTION `ISSUE_COUNT` (`node_id` INT) RETURNS INT(11) NO SQL
RETURN (SELECT issue_count FROM issue_count WHERE node = node_id)$$

CREATE DEFINER=`database`@`localhost` FUNCTION `IS_NODE_ACTIVE` (`node_id` INT) RETURNS TINYINT(4) NO SQL
  DETERMINISTIC
RETURN (SELECT UNIX_TIMESTAMP(last_active) FROM last_active WHERE node = node_id) > CURRENT_TIMESTAMP() - 200$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `account`
--

CREATE TABLE `account` (
                         `id` int(11) NOT NULL,
                         `discord_id` char(20) COLLATE latin1_german1_ci NOT NULL,
                         `pw_bcrypt` char(60) COLLATE latin1_german1_ci NOT NULL,
                         `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                         `slots` int(11) NOT NULL DEFAULT '5'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_call`
--

CREATE TABLE `api_call` (
                          `id` int(11) NOT NULL,
                          `node` int(11) NOT NULL,
                          `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `avg_stats`
--
CREATE TABLE `avg_stats` (
                           `peering` int(11)
  ,`of_node` int(11)
  ,`by_node` int(11)
  ,`avg_txs_all` decimal(14,4)
  ,`amount` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `bad_peering`
--
CREATE TABLE `bad_peering` (
                             `peering` int(11)
  ,`node_issue` int(11)
  ,`node_complaining` int(11)
);

-- --------------------------------------------------------

--
-- Table structure for table `error`
--

CREATE TABLE `error` (
                       `id` int(11) NOT NULL,
                       `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                       `query` varchar(1000) COLLATE latin1_german1_ci NOT NULL,
                       `error` varchar(1000) COLLATE latin1_german1_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `issue_count`
--
CREATE TABLE `issue_count` (
                             `node` int(11)
  ,`issue_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `last_active`
--
CREATE TABLE `last_active` (
                             `node` int(11)
  ,`last_active` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `node`
--

CREATE TABLE `node` (
                      `id` int(11) NOT NULL,
                      `account` int(11) NOT NULL,
                      `address` varchar(255) COLLATE latin1_german1_ci NOT NULL,
                      `static_nbs` int(11) NOT NULL,
                      `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      `timeout` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

--
-- Triggers `node`
--
DELIMITER $$
CREATE TRIGGER `GO_INTO_TIMEOUT` BEFORE UPDATE ON `node` FOR EACH ROW IF(OLD.timeout <= CURRENT_TIMESTAMP() && NEW.timeout > CURRENT_TIMESTAMP()) THEN

  DELETE FROM peering WHERE node1 = NEW.id || node2 = NEW.id;

END IF
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `peering`
--

CREATE TABLE `peering` (
                         `id` int(11) NOT NULL,
                         `node1` int(11) NOT NULL COMMENT 'node1 < node2',
                         `node2` int(11) NOT NULL COMMENT 'node2 > node1',
                         `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `peer_seeking_nodes`
--
CREATE TABLE `peer_seeking_nodes` (
  `id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `slots_free`
--
CREATE TABLE `slots_free` (
                            `account` int(11)
  ,`free` bigint(22)
);

-- --------------------------------------------------------

--
-- Table structure for table `stats`
--

CREATE TABLE `stats` (
                       `id` int(11) NOT NULL,
                       `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       `of_node` int(11) DEFAULT NULL,
                       `by_node` int(11) DEFAULT NULL,
                       `txs_all` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

--
-- Triggers `stats`
--
DELIMITER $$
CREATE TRIGGER `unpeer on bad stats` AFTER INSERT ON `stats` FOR EACH ROW BEGIN

  IF (SELECT 1 FROM bad_peering WHERE node_complaining = NEW.by_node AND node_issue = NEW.of_node)
  THEN
    INSERT INTO unpeering (node_issue, node_complaining) VALUES(NEW.of_node, NEW.by_node);
  END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `total_nbs`
--
CREATE TABLE `total_nbs` (
                           `node` int(11)
  ,`total_nbs` bigint(22)
);

-- --------------------------------------------------------

--
-- Table structure for table `unpeering`
--

CREATE TABLE `unpeering` (
                           `id` int(11) NOT NULL,
                           `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                           `node_complaining` int(11) NOT NULL,
                           `node_issue` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german1_ci;

--
-- Triggers `unpeering`
--
DELIMITER $$
CREATE TRIGGER `delete peering and update issue node` AFTER INSERT ON `unpeering` FOR EACH ROW BEGIN

  DELETE FROM peering WHERE node1 = LEAST(NEW.node_complaining, NEW.node_issue) && node2 = GREATEST(NEW.node_complaining, NEW.node_issue);

  UPDATE node SET timeout = GREATEST(timeout, CURRENT_TIMESTAMP() + 7200) WHERE id = NEW.node_issue AND ISSUE_COUNT(NEW.node_issue) >= 5;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `ensure peering exists` BEFORE INSERT ON `unpeering` FOR EACH ROW IF !EXISTS (SELECT 1 FROM peering where
    node1 = LEAST(NEW.node_complaining, NEW.node_issue) AND
    node2 = GREATEST(NEW.node_complaining, NEW.node_issue)
  )
THEN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Peering does not exist';
END IF
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure for view `avg_stats`
--
DROP TABLE IF EXISTS `avg_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`database`@`localhost` SQL SECURITY DEFINER VIEW `avg_stats`  AS  select `peering`.`id` AS `peering`,`stats`.`of_node` AS `of_node`,`stats`.`by_node` AS `by_node`,avg(`stats`.`txs_all`) AS `avg_txs_all`,count(`stats`.`id`) AS `amount` from (`stats` left join `peering` on(((`peering`.`node1` = least(`stats`.`of_node`,`stats`.`by_node`)) and (`peering`.`node2` = greatest(`stats`.`of_node`,`stats`.`by_node`))))) group by `stats`.`of_node`,`stats`.`by_node` ;

-- --------------------------------------------------------

--
-- Structure for view `bad_peering`
--
DROP TABLE IF EXISTS `bad_peering`;

CREATE ALGORITHM=UNDEFINED DEFINER=`database`@`localhost` SQL SECURITY DEFINER VIEW `bad_peering`  AS  select `avg_stats`.`peering` AS `peering`,`avg_stats`.`of_node` AS `node_issue`,`avg_stats`.`by_node` AS `node_complaining` from `avg_stats` where ((`avg_stats`.`avg_txs_all` < 20) and (`avg_stats`.`peering` is not null) and (`avg_stats`.`amount` > 5)) ;

-- --------------------------------------------------------

--
-- Structure for view `issue_count`
--
DROP TABLE IF EXISTS `issue_count`;

CREATE ALGORITHM=UNDEFINED DEFINER=`database`@`localhost` SQL SECURITY DEFINER VIEW `issue_count`  AS  select `unpeering`.`node_issue` AS `node`,count(`unpeering`.`id`) AS `issue_count` from `unpeering` where (`unpeering`.`created` > (now() - (3600 * 6))) group by `unpeering`.`node_issue` ;

-- --------------------------------------------------------

--
-- Structure for view `last_active`
--
DROP TABLE IF EXISTS `last_active`;

CREATE ALGORITHM=UNDEFINED DEFINER=`database`@`localhost` SQL SECURITY DEFINER VIEW `last_active`  AS  select `api_call`.`node` AS `node`,max(`api_call`.`created`) AS `last_active` from `api_call` group by `api_call`.`node` ;

-- --------------------------------------------------------

--
-- Structure for view `peer_seeking_nodes`
--
DROP TABLE IF EXISTS `peer_seeking_nodes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`database`@`localhost` SQL SECURITY DEFINER VIEW `peer_seeking_nodes`  AS  select `node`.`id` AS `id` from (`node` left join `total_nbs` on((`node`.`id` = `total_nbs`.`node`))) where ((`total_nbs`.`total_nbs` < 3) and (`node`.`timeout` < now())) ;

-- --------------------------------------------------------

--
-- Structure for view `slots_free`
--
DROP TABLE IF EXISTS `slots_free`;

CREATE ALGORITHM=UNDEFINED DEFINER=`database`@`localhost` SQL SECURITY DEFINER VIEW `slots_free`  AS  select `N`.`account` AS `account`,(`account`.`slots` - `N`.`used`) AS `free` from (((select count(`node`.`id`) AS `used`,`node`.`account` AS `account` from `node` group by `node`.`account`)) `N` join `account` on((`account`.`id` = `N`.`account`))) ;

-- --------------------------------------------------------

--
-- Structure for view `total_nbs`
--
DROP TABLE IF EXISTS `total_nbs`;

CREATE ALGORITHM=UNDEFINED DEFINER=`database`@`localhost` SQL SECURITY DEFINER VIEW `total_nbs`  AS  select `node`.`id` AS `node`,(`node`.`static_nbs` + count(`peering`.`id`)) AS `total_nbs` from (`node` left join `peering` on(((`node`.`id` = `peering`.`node1`) or (`node`.`id` = `peering`.`node2`)))) group by `node`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account`
--
ALTER TABLE `account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `discord_id_2` (`discord_id`),
  ADD KEY `discord_id` (`discord_id`),
  ADD KEY `discord_id_3` (`discord_id`),
  ADD KEY `discord_id_4` (`discord_id`);

--
-- Indexes for table `api_call`
--
ALTER TABLE `api_call`
  ADD PRIMARY KEY (`id`),
  ADD KEY `node` (`node`),
  ADD KEY `created` (`created`);

--
-- Indexes for table `error`
--
ALTER TABLE `error`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `node`
--
ALTER TABLE `node`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account` (`account`),
  ADD KEY `address` (`address`);

--
-- Indexes for table `peering`
--
ALTER TABLE `peering`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `node1_2` (`node1`,`node2`),
  ADD KEY `node1` (`node1`),
  ADD KEY `node2` (`node2`);

--
-- Indexes for table `stats`
--
ALTER TABLE `stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `of_node` (`of_node`),
  ADD KEY `by_node` (`by_node`),
  ADD KEY `created` (`created`),
  ADD KEY `of_node_2` (`of_node`,`by_node`);

--
-- Indexes for table `unpeering`
--
ALTER TABLE `unpeering`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created` (`created`),
  ADD KEY `node_complaining` (`node_complaining`),
  ADD KEY `node_issue` (`node_issue`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account`
--
ALTER TABLE `account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `api_call`
--
ALTER TABLE `api_call`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `error`
--
ALTER TABLE `error`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `node`
--
ALTER TABLE `node`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `peering`
--
ALTER TABLE `peering`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `stats`
--
ALTER TABLE `stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `unpeering`
--
ALTER TABLE `unpeering`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_call`
--
ALTER TABLE `api_call`
  ADD CONSTRAINT `api_call_ibfk_1` FOREIGN KEY (`node`) REFERENCES `node` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `node`
--
ALTER TABLE `node`
  ADD CONSTRAINT `node_ibfk_1` FOREIGN KEY (`account`) REFERENCES `account` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `peering`
--
ALTER TABLE `peering`
  ADD CONSTRAINT `peering_ibfk_1` FOREIGN KEY (`node1`) REFERENCES `node` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `peering_ibfk_2` FOREIGN KEY (`node2`) REFERENCES `node` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `stats`
--
ALTER TABLE `stats`
  ADD CONSTRAINT `stats_ibfk_1` FOREIGN KEY (`of_node`) REFERENCES `node` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT `stats_ibfk_2` FOREIGN KEY (`by_node`) REFERENCES `node` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION;
