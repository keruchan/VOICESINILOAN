-- Migration: add 'email' as a valid delivery channel for party_notifications.
-- The mediation-schedule notifier now sends a real email (via sendAppMail,
-- the same mailer used by the email-verification flow) when a party has a
-- linked account with an email on file. Without this, MySQL's SET type
-- silently drops any 'email' value that isn't a recognized member.

USE `voice2_db`;

ALTER TABLE `party_notifications`
  MODIFY `channel` set('inapp','sms','print','email') DEFAULT 'inapp';
