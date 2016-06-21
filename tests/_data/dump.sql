-- Add test users
INSERT INTO users (id, name, email, username, password) VALUES ('1', 'Test user','test@ushahidi.com','test@ushahidi.com','$2y$10$IuqAql1uP05eZ5ZEen3q1.6v4EhGbh6x7hOUsvR1x9FvI8jnbdRlC'), ('2', 'Admin user','admin@ushahidi.com','admin@ushahidi.com','$2y$10$IuqAql1uP05eZ5ZEen3q1.6v4EhGbh6x7hOUsvR1x9FvI8jnbdRlC');

-- Add test user roles
INSERT INTO roles (id, name) VALUES ('1', 'admin');
INSERT INTO roles (id, name) VALUES ('2', 'member');
INSERT INTO roles (id, name) VALUES ('3', 'login');

-- Insert admin role
INSERT INTO roles_users (user_id, role_id) VALUES ('1', '2');
INSERT INTO roles_users (user_id, role_id) VALUES ('2', '1');

-- Add OAuth tokens and scopes
-- Clients
INSERT INTO oauth_clients (id, secret) VALUES ('webapp', 'secret');
-- Scopes
INSERT INTO oauth_scopes (id) VALUES ('user'),('organization');

-- Client credentials
INSERT INTO oauth_access_token_scopes (access_token_id, scope_id) VALUES ('anonusertoken', 'user');
INSERT INTO oauth_sessions (client_id, owner_type, owner_id) VALUES ('webapp', 'client', 'webapp');
INSERT INTO oauth_session_scopes (session_id, scope_id) VALUES ('1', 'user');
INSERT INTO oauth_access_tokens VALUES ('anonusertoken',1,1856429714,'0000-00-00 00:00:00','0000-00-00 00:00:00');

-- Password grants
-- User
INSERT INTO oauth_access_token_scopes (access_token_id, scope_id) VALUES ('usertoken', 'user');
INSERT INTO oauth_sessions (client_id, owner_type, owner_id) VALUES ('webapp','user','1');
INSERT INTO oauth_session_scopes (session_id, scope_id) VALUES ('2','user');
INSERT INTO oauth_access_tokens VALUES ('usertoken',2,1856429714,'0000-00-00 00:00:00','0000-00-00 00:00:00');
-- Admin
INSERT INTO oauth_access_token_scopes (access_token_id, scope_id) VALUES ('admintoken', 'user');
INSERT INTO oauth_sessions (client_id, owner_type, owner_id) VALUES ('webapp','user','2');
INSERT INTO oauth_session_scopes (session_id, scope_id) VALUES ('3','user');
INSERT INTO oauth_access_tokens VALUES ('admintoken',3,1856429714,'0000-00-00 00:00:00','0000-00-00 00:00:00');
-- Organization Admin
INSERT INTO oauth_access_token_scopes (access_token_id, scope_id) VALUES ('orgadmintoken', 'organization');
INSERT INTO oauth_sessions (client_id, owner_type, owner_id) VALUES ('webapp','user','1');
INSERT INTO oauth_session_scopes (session_id, scope_id) VALUES ('4', 'organization');
INSERT INTO oauth_access_tokens VALUES ('orgadmintoken',4,1856429714,'0000-00-00 00:00:00','0000-00-00 00:00:00');
	
-- Add test organizations
INSERT INTO organizations (id, name, url) VALUES ('1', 'Test organization', 'test@rollcall.io');
INSERT INTO organizations (id, name, url) VALUES ('2', 'RollCall', 'rollcall@rollcall.io');

--Add test organization admins
INSERT INTO organization_admins(organization_id, user_id) VALUES ('1', '1');
