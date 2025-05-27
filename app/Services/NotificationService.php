<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a user.
     *
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param string $type
     * @param int|null $referenceId
     * @param bool $sendEmail
     * @return \App\Models\Notification
     */
    public function sendNotification($userId, $title, $message, $type, $referenceId = null, $sendEmail = false)
    {
        try {
            // Create notification record
            $notification = Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'reference_id' => $referenceId,
                'is_read' => false,
            ]);
            
            // Send email notification if required
            if ($sendEmail) {
                $this->sendEmailNotification($userId, $title, $message, $type);
            }
            
            return $notification;
        } catch (\Exception $e) {
            Log::error("Failed to send notification: {$e->getMessage()}");
            return null;
        }
    }
    
    /**
     * Send a notification to multiple users.
     *
     * @param array $userIds
     * @param string $title
     * @param string $message
     * @param string $type
     * @param int|null $referenceId
     * @param bool $sendEmail
     * @return array
     */
    public function sendBulkNotifications($userIds, $title, $message, $type, $referenceId = null, $sendEmail = false)
    {
        $notifications = [];
        
        foreach ($userIds as $userId) {
            $notification = $this->sendNotification($userId, $title, $message, $type, $referenceId, $sendEmail);
            if ($notification) {
                $notifications[] = $notification;
            }
        }
        
        return $notifications;
    }
    
    /**
     * Send a notification to users with specific roles.
     *
     * @param array $roles
     * @param string $title
     * @param string $message
     * @param string $type
     * @param int|null $referenceId
     * @param bool $sendEmail
     * @return array
     */
    public function sendNotificationToRole($roles, $title, $message, $type, $referenceId = null, $sendEmail = false)
    {
        $users = User::whereHas('roles', function($query) use ($roles) {
            $query->whereIn('slug', $roles);
        })->pluck('id')->toArray();
        
        return $this->sendBulkNotifications($users, $title, $message, $type, $referenceId, $sendEmail);
    }
    
    /**
     * Send an email notification.
     *
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param string $type
     * @return bool
     */
    protected function sendEmailNotification($userId, $title, $message, $type)
    {
        try {
            $user = User::find($userId);
            
            if (!$user || !$user->email) {
                return false;
            }
            
            // Determine email template based on notification type
            $template = 'emails.notification';
            switch ($type) {
                case 'LOAN_APPLICATION':
                    $template = 'emails.loan-application';
                    break;
                case 'LOAN_APPROVAL':
                    $template = 'emails.loan-approval';
                    break;
                case 'LOAN_DISBURSEMENT':
                    $template = 'emails.loan-disbursement';
                    break;
                case 'LOAN_REPAYMENT':
                    $template = 'emails.loan-repayment';
                    break;
                case 'LOAN_DEFAULT':
                    $template = 'emails.loan-default';
                    break;
                // Add more email templates as needed
            }
            
            // Send the email
            Mail::send($template, [
                'user' => $user,
                'title' => $title,
                'message' => $message,
                'type' => $type,
            ], function($mail) use ($user, $title) {
                $mail->to($user->email, $user->name)
                    ->subject($title);
            });
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email notification: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Send an SMS notification.
     *
     * @param int $userId
     * @param string $message
     * @return bool
     */
    public function sendSMSNotification($userId, $message)
    {
        try {
            $user = User::find($userId);
            
            if (!$user || !$user->phone) {
                return false;
            }
            
            // This would integrate with an SMS service provider
            // For now, we'll log the SMS message
            Log::info("SMS notification to {$user->phone}: {$message}");
            
            /*
            // Example integration with an SMS service
            $smsService = new SmsService();
            $response = $smsService->send($user->phone, $message);
            return $response['success'];
            */
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send SMS notification: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Mark a notification as read.
     *
     * @param int $notificationId
     * @return bool
     */
    public function markAsRead($notificationId)
    {
        try {
            $notification = Notification::find($notificationId);
            
            if (!$notification) {
                return false;
            }
            
            $notification->update(['is_read' => true]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to mark notification as read: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user.
     *
     * @param int $userId
     * @return bool
     */
    public function markAllAsRead($userId)
    {
        try {
            Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to mark all notifications as read: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Get unread notification count for a user.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount($userId)
    {
        try {
            return Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->count();
        } catch (\Exception $e) {
            Log::error("Failed to get unread notification count: {$e->getMessage()}");
            return 0;
        }
    }
    
    /**
     * Get recent notifications for a user.
     *
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentNotifications($userId, $limit = 5)
    {
        try {
            return Notification::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to get recent notifications: {$e->getMessage()}");
            return collect([]);
        }
    }
} 