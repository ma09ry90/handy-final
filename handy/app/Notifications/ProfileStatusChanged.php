<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ProfileStatusChanged extends Notification
{
    use Queueable;

    protected $profile;
    protected $status;
    protected $reason;

    public function __construct($profile, $status, $reason = null)
    {
        $this->profile = $profile;
        $this->status = $status;
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject('Profile '.$this->status)
            ->greeting('Hello '.$notifiable->first_name)
            ->line('Your profile status is now: '.strtoupper($this->status));

        if ($this->reason) {
            $mail->line('Reason: '.$this->reason);
        }

        $mail->action('View Profile', url('/profile'));

        return $mail;
    }

    public function toArray($notifiable)
    {
        return [
            'profile_id' => $this->profile->id,
            'profile_type' => class_basename($this->profile),
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
