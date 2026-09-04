import { useEffect, useState } from 'react'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { paymentService } from '@/services/payment'
import type { Payment, Sale } from '@/types'

interface QRISPaymentModalProps {
  sale: Sale | null
  payment: Payment | null
  open: boolean
  onClose: () => void
  onSuccess: () => void
}

export function QRISPaymentModal({ sale, payment, open, onClose, onSuccess }: QRISPaymentModalProps) {
  const [status, setStatus] = useState<string>(payment?.status ?? 'pending')
  const [polling, setPolling] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const qrString = (payment?.metadata?.qr_string as string | undefined) ?? ''
  const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(qrString)}`

  useEffect(() => {
    if (!open || !sale || !payment || payment.status === 'success') return

    setPolling(true)
    const interval = setInterval(async () => {
      try {
        const updated = await paymentService.getChargeStatus(sale.id, payment.id)
        setStatus(updated.status)

        if (updated.status === 'success') {
          clearInterval(interval)
          setPolling(false)
          onSuccess()
        }

        if (updated.status === 'failed') {
          clearInterval(interval)
          setPolling(false)
          setError('Payment failed or expired')
        }
      } catch (e) {
        console.error('QRIS poll failed', e)
      }
    }, 3000)

    return () => {
      clearInterval(interval)
      setPolling(false)
    }
  }, [open, sale, payment, onSuccess])

  const isSuccess = status === 'success'
  const isFailed = status === 'failed'

  return (
    <Modal open={open} onClose={onClose} title="QRIS Payment">
      <div className="flex flex-col items-center gap-4 py-4">
        {!isSuccess && !isFailed && qrString && (
          <>
            <p className="text-sm text-gray-500">
              Scan this QR code with your e-wallet or banking app.
            </p>
            <img
              src={qrImageUrl}
              alt="QRIS Payment Code"
              className="border rounded-lg p-2"
            />
            <div className="flex items-center gap-2 text-sm text-gray-500">
              {polling && <span className="animate-spin h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full" />}
              Waiting for payment...
            </div>
          </>
        )}

        {isSuccess && (
          <div className="text-center text-green-600 font-semibold py-8">
            Payment successful!
          </div>
        )}

        {isFailed && (
          <div className="text-center text-red-600 font-semibold py-8">
            {error || 'Payment failed or expired.'}
          </div>
        )}
      </div>

      <div className="flex justify-end">
        <Button variant="outline" onClick={onClose}>
          Close
        </Button>
      </div>
    </Modal>
  )
}
