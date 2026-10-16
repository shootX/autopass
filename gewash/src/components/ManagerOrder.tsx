import React from 'react'
import clsx from 'clsx'
import {
  managerCallIconUrl,
  managerRefreshIconUrl,
  managerConfirmedIconUrl,
  managerVectorIconUrl,
  managerCancelIconUrl,
} from '@/assets/staticUrls'
import { formatPhone } from '@/lib/format'

type Status = 'Confirm' | 'Rescheduled' | 'Expired' | 'Deleted' | 'New' | 'Completed'

interface ManagerOrderProps {
  status?: Status
  localizedStatus: string
  date: string
  type: string
  customer: { name: string; phone: string }
  onDelete: () => void
  onReschedule?: () => void
  onConfirmed: () => void
}

const statusColorMap: Record<
  Status,
  {
    capBg: string;
    capText: string;
    labelBg: string;
    labelText: string;
  }
> = {
  Confirm: {
    capBg: "#B0EFBC",
    capText: "#12284b",
    labelBg: "#1E9E5A",
    labelText: "#FFFFFF",
  },
  Completed: {
    capBg: "#B0EFBC", capText: "#12284b", labelBg: "#1E9E5A", labelText: "#FFFFFF",
  },
  Rescheduled: {
    capBg: "#a8b3c4",
    capText: "#12284b",
    labelBg: "#12284b",
    labelText: "#e4b23c",
  },
  Expired: {
    capBg: "#FFC6C6",
    capText: "#12284b",
    labelBg: "#D64541",
    labelText: "#e4b23c",
  },
  Deleted: {
    capBg: "#a8b3c4",
    capText: "#FFFFFF",
    labelBg: "#e4ddd0",
    labelText: "#a8b3c4",
  },
  New: {
    capBg: "#12284b",
    capText: "#e4b23c",
    labelBg: "#e4b23c",
    labelText: "#12284b",
  },
};


function getStatusStyle(status: Status): React.CSSProperties {
  const { labelBg, labelText } = statusColorMap[status]

  const correctedBg = status === 'Deleted' ? '#e4ddd0' : labelBg

  return {
    backgroundColor: correctedBg,
    color: labelText,
    borderRadius: '8px',
    padding: '6px 12px',
    fontWeight: 600,
    fontSize: '1rem',
  }
}

export default function ManagerOrder({
  status = 'New',
  localizedStatus,
  date,
  customer,
  type,
  onDelete,
  onReschedule,
  onConfirmed
}: ManagerOrderProps) {
  const isConfirmed = status === 'Confirm'
  const isNew = status === 'New'
  const isDeleted = status === 'Deleted'
  const isTerminal = isDeleted || status === 'Completed' || status === 'Expired'
  const capStyle = {
    backgroundColor: statusColorMap[status].capBg,
    color: statusColorMap[status].capText,
  }

  const renderControlButtons = () => {
    return (
      <div className='order-controll-right-box'>
        <a href={`tel:+${customer.phone}`}>
          <button className='order-btn'>
            <img src={managerCallIconUrl} alt='cutomerCall' />
          </button>
        </a>
        {!isConfirmed && !isTerminal && onReschedule && (
          <button
            className='order-btn'
            onClick={onReschedule}
            disabled={isTerminal}
          >
            <img src={managerRefreshIconUrl} alt='refresh' />
          </button>
        )}
        <button className='order-btn' disabled={isTerminal} onClick={onConfirmed}>
          <img src={managerConfirmedIconUrl} alt='confirmed' />

        </button>
      </div>
    )
  }

  return (
    <div
      className={clsx("manager-order-container", { "gray-scale": isDeleted })}
    >
      <div className='order-cap' style={capStyle}>
        <h4 style={{ color: statusColorMap[status].capText }}>{date}</h4>
        <span className='order-status' style={getStatusStyle(status)}>
          {localizedStatus}
        </span>
      </div>

      <div className='order-info'>
        <div className='washing-type-container'>
          <img
            src={managerVectorIconUrl}
            alt='washing-type-icon'
          />
          <p className='washing-type'>{type}</p>
        </div>

        <div className='customer-order-info'>
          <p className='customer-order-name'>{customer.name}</p>
          <p className='customer-order-phone-number'>{formatPhone(customer.phone)}</p>
        </div>

        <div className='order-controll-panel'>
          <div>
            <button
              className='order-btn cancel'
              onClick={onDelete}
              disabled={isTerminal}
            >
              <img
                src={managerCancelIconUrl}
                alt='cancel-btn'
              />
            </button>
          </div>

          {(isNew || isDeleted) && renderControlButtons()}

          {status !== "New" && !isTerminal && (
            <div className='order-controll-right-box'>
              <a href={`tel:+${customer.phone}`}>
                <button className='order-btn'>
                  <img
                    src={managerCallIconUrl}
                    alt='cutomerCall'
                  />
                </button>
              </a>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
