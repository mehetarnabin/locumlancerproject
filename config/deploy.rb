# config valid only for current version of Capistrano
lock "3.19.1"

set :repo_url, "git@gitlab.com:anubhabi/locumlancer.git"

set :application, "locumlancer"

set :composer_install_flags, '--no-dev --no-interaction --optimize-autoloader'

set :config_path, "config"
set :web_path, "public"
set :var_path, "var"
set :bin_path, "bin"

set :log_path, "var/log"
set :cache_path, "var/cache"

set :symfony_console_path, "bin/console"
set :symfony_console_flags, "--no-debug"

set :update_vendors, false
set :copy_vendors, true

set :controllers_to_clear, ["app_*.php"]

set :permission_method, :acl

namespace :deploy do
  after :starting, 'composer:install_executable'
end

set :ssh_options, {
  forward_agent: true,
  auth_methods: ["publickey"]
}
# Default value for :linked_files is []
set :linked_files, [".env"]
set :linked_dirs, ["var/log"]

# Default value for linked_dirs is []
append :linked_dirs, "public/media", "public/uploads"

Rake::Task["deploy:set_permissions:acl"].clear_actions

set :file_permissions_users, ["www-data"]
# set :file_permissions_paths, ["var/cache"]

desc 'Start SSH agent and add all keys'
task :sshagent do
  run_locally  do
  	execute 'eval `ssh-agent -s` '
  	execute 'ssh-add -k'
  end
end

namespace :deploy do
  desc "Dumping assetic assets"
  task :assetic_dump do
    on roles(:all) do
      symfony_console "assetic:dump"
    end
  end

  desc "Set proper permmissions"
  task :custom_acl do
    next unless any? :file_permissions_paths
    on roles fetch(:file_permissions_roles) do |host|
      users = fetch(:file_permissions_users).push(host.user)
      entries = acl_entries(users);
      paths = absolute_writable_paths

      if any? :file_permissions_groups
        entries.push(*acl_entries(fetch(:file_permissions_groups), 'g'))
      end

      entries = entries.map { |e| "-m #{e}" }.join(' ')

      execute :setfacl, "-R", entries, *paths
      execute :setfacl, "-dR", entries, *paths.map
    end
  end
end

# before 'deploy:starting', 'sshagent'

on :start do |task|
  # puts task
  invoke "sshagent"
end

after 'deploy:updated', 'symfony:assets:install'
after 'symfony:create_cache_dir', 'deploy:custom_acl'

# Default branch is :master
# ask :branch, `git rev-parse --abbrev-ref HEAD`.chomp

# Default deploy_to directory is /var/www/my_app_name
# set :deploy_to, "/var/www/my_app_name"

# Default value for :format is :airbrussh.
# set :format, :airbrussh

# You can configure the Airbrussh format using :format_options.
# These are the defaults.
# set :format_options, command_output: true, log_file: "log/capistrano.log", color: :auto, truncate: :auto

# Default value for :pty is false
# set :pty, true

# Default value for :linked_files is []
# append :linked_files, "config/database.yml", "config/secrets.yml"

# Default value for linked_dirs is []
# append :linked_dirs, "log", "tmp/pids", "tmp/cache", "tmp/sockets", "public/system"

# Default value for default_env is {}
# set :default_env, { path: "/opt/ruby/bin:$PATH" }

# Default value for local_user is ENV['USER']
# set :local_user, -> { `git config user.name`.chomp }

# Default value for keep_releases is 5
set :keep_releases, 3